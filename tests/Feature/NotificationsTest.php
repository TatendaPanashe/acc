<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Platform\Payment;
use App\Models\Platform\PlatformInvoice;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Services\CommercialPostingService;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\LeaveType;
use App\Modules\Payroll\Models\PayrollRun;
use App\Notifications\Account\NewSignupAlert;
use App\Notifications\Account\SubscriptionChanged;
use App\Notifications\Account\SubscriptionPaymentFailed;
use App\Notifications\Account\SubscriptionPaymentReceived;
use App\Notifications\Account\TrialEndingSoon;
use App\Notifications\Account\WelcomeToWorkspace;
use App\Notifications\Customers\CustomerInvoiceIssued;
use App\Notifications\Customers\CustomerInvoiceOverdue;
use App\Notifications\Payroll\PayslipReady;
use App\Notifications\Payroll\SelfServiceRequestDecided;
use App\Notifications\Payroll\SelfServiceRequestSubmitted;
use App\Notifications\PortalAccessIssued;
use App\Notifications\Team\ApprovalDecided;
use App\Notifications\Team\PendingApprovalsDigest;
use App\Notifications\Team\TeamMemberAdded;
use App\Services\Notifications\Notifier;
use App\Services\Platform\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $admin;
    private User $manager;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-10 09:00:00');
        Notification::fake();
        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->manager = $this->ledgerUser($this->company, 'finance_manager');
        $this->accountant = $this->ledgerUser($this->company, 'accountant');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        // Every email a test sent must render.
        $fake = Notification::getFacadeRoot();
        if ($fake instanceof \Illuminate\Support\Testing\Fakes\NotificationFake) {
            foreach ($fake->sentNotifications() as $byId) {
                foreach ($byId as $byClass) {
                    foreach ($byClass as $sent) {
                        foreach ($sent as $item) {
                            $this->assertNotEmpty((string) $item['notification']->toMail($item['notifiable'])->render());
                        }
                    }
                }
            }
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function countOnDemand(string $email, string $class): int
    {
        $count = 0;
        foreach (Notification::sentNotifications()[AnonymousNotifiable::class] ?? [] as $byId) {
            foreach ($byId[$class] ?? [] as $sent) {
                $count += ($sent['notifiable']->routes['mail'] ?? null) === $email ? 1 : 0;
            }
        }

        return $count;
    }

    private function enable(string ...$events): void
    {
        $this->actingAs($this->admin)->put(route('company.notifications.update'), ['events' => array_fill_keys($events, 1)])->assertSessionHas('success');
    }

    // ---- account & subscription ------------------------------------------------

    public function test_sign_up_alerts_the_platform_and_welcomes_once_the_email_is_confirmed(): void
    {
        $this->usersStartVerified = false;
        config(['notifications.platform_email' => 'ops@acc263.test']);
        auth()->logout();
        $this->post('/register', ['name' => 'Tendai', 'email' => 'tendai@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123', 'plan' => 'essential']);

        $user = User::where('email', 'tendai@example.com')->firstOrFail();
        Notification::assertSentTo($user, \App\Notifications\Account\VerifyEmailAddress::class);
        Notification::assertNotSentTo($user, WelcomeToWorkspace::class);

        $this->get(\Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]));
        Notification::assertSentTo($user, WelcomeToWorkspace::class, fn ($n) => $n->trialEndsOn === '24 Jun 2026' && str_contains($n->toMail($user)->render(), 'Essential'));
        $this->assertSame(1, $this->countOnDemand('ops@acc263.test', NewSignupAlert::class));
        // Picking a plan at sign-up is not reported as a separate "plan changed" email.
        Notification::assertNotSentTo($user, SubscriptionChanged::class);
    }

    public function test_sign_up_works_from_a_browser_still_holding_a_deleted_users_session(): void
    {
        $this->app['auth']->forgetGuards(); // a fresh browser visit: no one signed in yet
        $stale = [auth()->guard()->getName() => 999999]; // ...but the session remembers a user that no longer exists

        $this->withSession($stale)->post('/register', ['name' => 'Tatenda', 'email' => 'tatenda@example.com', 'password' => 'Password123', 'password_confirmation' => 'Password123'])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'tatenda@example.com')->firstOrFail();
        $this->assertNull(DB::table('platform_subscription_events')->where('company_id', $user->company_id)->where('type', 'trial_started')->value('user_id'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_requests_send_after_the_response_without_a_queue_worker(): void
    {
        config(['notifications.delivery' => 'after_response', 'queue.default' => 'database']);
        $console = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $console->setValue($this->app, false); // as in a web request

        try {
            app(Notifier::class)->send('account.welcome', $this->admin, new WelcomeToWorkspace($this->admin), $this->company->id);
            Notification::assertNotSentTo($this->admin, WelcomeToWorkspace::class); // not while the page is being served

            $this->app->terminate(); // the browser has its response
            Notification::assertSentTo($this->admin, WelcomeToWorkspace::class);
            $this->assertSame(0, DB::table('jobs')->count()); // nothing left waiting for a worker
        } finally {
            $console->setValue($this->app, true);
        }
    }

    public function test_subscription_payments_send_a_receipt_or_a_failure_notice(): void
    {
        $this->post(route('billing.checkout'), ['plan' => 'professional', 'billing_cycle' => 'monthly'])->assertSessionHasNoErrors();
        $invoice = PlatformInvoice::where('company_id', $this->company->id)->latest('id')->firstOrFail();

        // A declined payment.
        $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'sandbox']);
        $declined = Payment::where('platform_invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->post(route('billing.sandbox.complete', $declined), ['outcome' => 'failed']);
        Notification::assertSentTo($this->admin, SubscriptionPaymentFailed::class);
        Notification::assertNotSentTo($this->manager, SubscriptionPaymentFailed::class); // admins only

        // Then a successful one.
        $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'sandbox']);
        $paid = Payment::where('platform_invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->post(route('billing.sandbox.complete', $paid), ['outcome' => 'success']);
        $this->get(route('billing.payments.return', $paid));
        Notification::assertSentToTimes($this->admin, SubscriptionPaymentReceived::class, 1);
        Notification::assertSentTo($this->admin, SubscriptionPaymentReceived::class, fn ($n) => str_contains($n->toMail($this->admin)->render(), $invoice->number));
    }

    public function test_trial_reminder_is_sent_once_per_reminder_day(): void
    {
        $sub = $this->company->fresh()->subscription;
        $sub->forceFill(['status' => 'trialing', 'trial_ends_at' => now()->addDays(3)->subHour()])->save();

        $this->artisan('billing:run')->assertSuccessful();
        $this->artisan('billing:run')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, TrialEndingSoon::class, 1);

        // Two days later: the 1-day reminder, once.
        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('billing:run')->assertSuccessful();
        $this->artisan('billing:run')->assertSuccessful();
        Notification::assertSentToTimes($this->admin, TrialEndingSoon::class, 2);
    }

    public function test_suspension_and_reactivation_email_the_admins(): void
    {
        $service = app(SubscriptionService::class);
        $sub = $service->suspend($this->company->fresh()->subscription, 'Payment not received', 'billing');
        $service->reactivate($sub);

        Notification::assertSentTo($this->admin, SubscriptionChanged::class, fn ($n) => $n->event->type === 'suspended'
            && str_contains($n->toMail($this->admin)->subject, 'suspended'));
        Notification::assertSentTo($this->admin, SubscriptionChanged::class, fn ($n) => $n->event->type === 'reactivated');
    }

    // ---- team & approvals -------------------------------------------------------

    public function test_new_team_member_is_told_without_their_password(): void
    {
        $this->post(route('company.team.store'), ['name' => 'Rudo', 'username' => 'rudo', 'email' => 'rudo@example.com', 'role' => 'accountant',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])->assertSessionHasNoErrors();

        $rudo = User::where('email', 'rudo@example.com')->firstOrFail();
        Notification::assertSentTo($rudo, TeamMemberAdded::class, fn ($n) => !str_contains($n->toMail($rudo)->render(), 'secret-pass-1'));
    }

    public function test_preparer_hears_about_decisions_and_approvers_get_a_digest(): void
    {
        $this->actingAs($this->accountant)->post(route('accounting.gl.journals.store'), [
            'entry_number' => 'JV-100', 'entry_date' => '2026-06-10', 'currency' => 'USD', 'memo' => 'Accrual',
            'lines' => [['account_id' => $this->ledgerAccount($this->company, '6000')->id, 'debit' => 250], ['account_id' => $this->ledgerAccount($this->company, '2300')->id, 'credit' => 250]],
        ])->assertSessionHasNoErrors();
        $journal = JournalEntry::where('entry_number', 'JV-100')->firstOrFail();

        $this->artisan('notifications:daily')->assertSuccessful();
        $this->artisan('notifications:daily')->assertSuccessful(); // once a day
        Notification::assertSentToTimes($this->manager, PendingApprovalsDigest::class, 1);
        Notification::assertSentTo($this->admin, PendingApprovalsDigest::class, fn ($n) => $n->counts === ['Journal entry' => 1]);
        Notification::assertNotSentTo($this->accountant, PendingApprovalsDigest::class);

        $this->actingAs($this->manager)->post(route('accounting.approvals-audit.reject', ['journal', $journal->id]), ['note' => 'Attach the schedule'])->assertSessionHasNoErrors();
        Notification::assertSentTo($this->accountant, ApprovalDecided::class, fn ($n) => $n->decision === 'rejected' && $n->note === 'Attach the schedule' && $n->decidedBy === $this->manager->name);

        // Nobody is emailed about their own decision.
        app(\App\Modules\Accounting\Services\Approvals\ApprovalPolicy::class)->record($journal->fresh(), 'journal', 'approved', $this->accountant);
        Notification::assertSentToTimes($this->accountant, ApprovalDecided::class, 1);
    }

    // ---- payroll ------------------------------------------------------------------

    private function payrollSettings(): void
    {
        $this->actingAs($this->admin)->post(route('accounting.payroll.settings.store'), [
            'tax_year' => 2026, 'currency' => 'USD', 'nssa_employee_rate' => 0, 'nssa_employer_rate' => 0, 'aids_levy_rate' => 0,
            'bands' => [['lower' => 0, 'upper' => null, 'rate' => 10, 'deduct' => 0]],
        ])->assertSessionHasNoErrors();
    }

    private function employee(string $number, string $email): Employee
    {
        $employee = new Employee(['employee_number' => $number, 'first_name' => 'Tendai', 'last_name' => 'Moyo', 'email' => $email, 'start_date' => '2025-01-01',
            'basic_salary' => 1000, 'salary_currency' => 'USD', 'payment_method' => 'cash', 'status' => 'active']);
        $employee->company_id = $this->company->id;
        $employee->saveQuietly();

        return $employee;
    }

    public function test_portal_access_leave_requests_and_decisions_are_emailed(): void
    {
        $employee = $this->employee('EMP0001', 'tendai@example.com');

        $this->actingAs($this->manager)->post(route('accounting.payroll.employees.portal.issue', $employee))->assertSessionHas('portal_password');
        $temporary = session('portal_password');
        $this->assertSame(1, $this->countOnDemand('tendai@example.com', PortalAccessIssued::class));
        Notification::assertSentOnDemand(PortalAccessIssued::class, fn ($n) => $n->password === $temporary && $n instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted);

        auth()->logout();
        $this->post(route('staff.login.submit'), ['email' => $employee->email, 'password' => $temporary]);
        $this->post(route('staff.password.update'), ['current_password' => $temporary, 'password' => 'my-new-password', 'password_confirmation' => 'my-new-password']);
        $this->get(route('staff.leave'))->assertOk();
        $this->post(route('staff.leave.store'), ['leave_type_id' => LeaveType::withoutGlobalScopes()->where('company_id', $this->company->id)->value('id'),
            'starts_on' => '2026-06-15', 'ends_on' => '2026-06-16', 'reason' => 'Family visit'])->assertSessionHasNoErrors();
        $this->post(route('staff.logout'));

        Notification::assertSentTo([$this->admin, $this->manager], SelfServiceRequestSubmitted::class);
        Notification::assertNotSentTo($this->accountant, SelfServiceRequestSubmitted::class);

        $leave = LeaveRequest::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->manager)->post(route('accounting.payroll.requests.leave.decide', $leave), ['decision' => 'approve'])->assertSessionHasNoErrors();
        Notification::assertSentOnDemand(SelfServiceRequestDecided::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'tendai@example.com'
            && str_contains($n->toMail($notifiable)->subject, 'approved'));
    }

    public function test_payslips_are_emailed_once_on_approval(): void
    {
        $this->payrollSettings();
        $withPortal = $this->employee('EMP0001', 'tendai@example.com');
        $withPortal->forceFill(['portal_password' => bcrypt('x')])->saveQuietly();
        $this->employee('EMP0002', 'rudo@example.com');

        $this->actingAs($this->accountant)->post(route('accounting.payroll.runs.store'), ['period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'pay_date' => '2026-06-28']);
        $run = PayrollRun::latest('id')->firstOrFail();
        $this->post(route('accounting.payroll.runs.calculate', $run))->assertSessionHas('success');
        Notification::assertNothingSent(); // nothing until approval

        $this->actingAs($this->manager)->post(route('accounting.payroll.runs.approve', $run))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->countOnDemand('tendai@example.com', PayslipReady::class));
        $this->assertSame(1, $this->countOnDemand('rudo@example.com', PayslipReady::class));

        // Portal users get a link; others get the PDF attached. Both are branded as the company.
        $mailFor = function (string $email) {
            $found = null;
            Notification::assertSentOnDemand(PayslipReady::class, function ($n, $c, $notifiable) use ($email, &$found) {
                return $notifiable->routes['mail'] === $email && ($found = $n->toMail($notifiable));
            });

            return $found;
        };
        $portal = $mailFor('tendai@example.com');
        $attached = $mailFor('rudo@example.com');
        $this->assertSame([[], route('staff.payslips')], [$portal->rawAttachments, $portal->actionUrl]);
        $this->assertCount(1, $attached->rawAttachments);
        $this->assertStringStartsWith('Acme Ltd', $attached->from[1]);

        $this->assertSame(0, app(\App\Modules\Payroll\Services\PayrollRunService::class)->sendPayslips($run->fresh())); // never twice
    }

    // ---- customers ------------------------------------------------------------------

    private function customerInvoice(string $due = '2026-06-10', ?string $date = null): SalesInvoice
    {
        $customer = Customer::create(['customer_number' => 'C'.uniqid(), 'name' => 'Client', 'email' => 'accounts@client.test', 'credit_limit' => 0, 'status' => true]);
        $this->actingAs($this->admin)->post(route('accounting.ar.invoices.store'), [
            'customer_id' => $customer->id, 'invoice_number' => $number = 'INV-'.uniqid(), 'invoice_date' => $date ?? min($due, '2026-06-10'), 'due_date' => $due, 'currency' => 'USD',
            'lines' => [['revenue_account_id' => $this->ledgerAccount($this->company, '4000')->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]],
        ])->assertSessionHasNoErrors();

        return SalesInvoice::where('invoice_number', $number)->firstOrFail();
    }

    public function test_customer_emails_are_off_until_the_company_switches_them_on(): void
    {
        app(CommercialPostingService::class)->postSalesInvoice($this->customerInvoice());
        Notification::assertNothingSentTo(new AnonymousNotifiable);
        $this->assertSame(0, $this->countOnDemand('accounts@client.test', CustomerInvoiceIssued::class));

        $this->enable('customers.invoice_issued', 'team.member_added');
        $invoice = $this->customerInvoice();
        app(CommercialPostingService::class)->postSalesInvoice($invoice);
        app(CommercialPostingService::class)->postSalesInvoice($invoice); // already posted: no second email
        $this->assertSame(1, $this->countOnDemand('accounts@client.test', CustomerInvoiceIssued::class));
        Notification::assertSentOnDemand(CustomerInvoiceIssued::class, fn ($n, $c, $notifiable) => count($n->toMail($notifiable)->rawAttachments) === 1);

        // Settings saved: only the switched-on optional events are on; locked ones stay on.
        $notifier = app(Notifier::class);
        $this->assertTrue($notifier->enabled('customers.invoice_issued', $this->company->id));
        $this->assertFalse($notifier->enabled('approvals.digest', $this->company->id));
        $this->assertTrue($notifier->enabled('billing.invoice_due', $this->company->id));
        $this->get(route('company.notifications.edit'))->assertOk()->assertSee('Email invoices to customers')->assertSee('accounts@client.test');
        $this->actingAs($this->accountant)->get(route('company.notifications.edit'))->assertForbidden();
    }

    public function test_overdue_reminders_follow_the_reminder_days(): void
    {
        $this->enable('customers.overdue_reminder');
        $invoice = $this->customerInvoice('2026-06-10');
        app(CommercialPostingService::class)->postSalesInvoice($invoice);
        $stale = $this->customerInvoice('2026-03-01'); // long overdue before reminders were switched on
        app(CommercialPostingService::class)->postSalesInvoice($stale);

        $this->artisan('notifications:daily'); // not yet overdue
        $this->assertSame(0, $this->countOnDemand('accounts@client.test', CustomerInvoiceOverdue::class));

        foreach (['2026-06-11', '2026-06-11', '2026-06-12', '2026-06-17'] as $day) {
            Carbon::setTestNow($day.' 07:00:00');
            $this->artisan('notifications:daily');
        }
        // Day 1 and day 7, each once; the stale invoice is left alone.
        $this->assertSame(2, $this->countOnDemand('accounts@client.test', CustomerInvoiceOverdue::class));
        Notification::assertSentOnDemand(CustomerInvoiceOverdue::class, fn ($n) => $n->invoice->is($invoice) && $n->daysOverdue === 7);
    }

    // ---- delivery guarantees ------------------------------------------------------------

    public function test_nothing_is_sent_when_the_change_is_rolled_back(): void
    {
        try {
            DB::transaction(function () {
                app(SubscriptionService::class)->suspend($this->company->fresh()->subscription, 'Test', 'admin');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('notification_log')->count());
    }

    public function test_a_mail_failure_does_not_break_the_action(): void
    {
        Notification::swap(new class extends \Illuminate\Support\Testing\Fakes\NotificationFake {
            public function send($notifiables, $notification): void
            {
                throw new \RuntimeException('SMTP down');
            }
        });

        $this->post(route('company.team.store'), ['name' => 'Rudo', 'username' => 'rudo', 'email' => 'rudo@example.com', 'role' => 'accountant',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])->assertSessionHasNoErrors()->assertRedirect(route('company.team.index'));
        $this->assertDatabaseHas('users', ['email' => 'rudo@example.com']);
    }
}

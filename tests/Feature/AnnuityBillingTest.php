<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class AnnuityBillingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $accountant;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-10 09:00:00');
        $this->company = $this->createLedgerCompany();
        $this->ledgerUser($this->company, 'admin');
        $this->accountant = $this->ledgerUser($this->company, 'accountant');
        $this->actingAs($this->accountant);
        // Keep the workspace open while the tests move the clock forward by months.
        $sub = app(\App\Services\Platform\SubscriptionService::class)->activate($this->company->subscription, \App\Models\Platform\Plan::where('code', 'business')->first(), 'annual');
        $sub->update(['current_period_ends_at' => '2030-01-01']);
        $this->customer = Customer::create(['customer_number' => 'C1', 'name' => 'Mbare Clinic', 'credit_limit' => 0, 'currency' => 'USD', 'status' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function goTo(string $date): void
    {
        Carbon::setTestNow($date.' 09:00:00');
    }

    private function contract(array $overrides = []): Subscription
    {
        $this->post(route('accounting.annuity-billing.contracts.store'), $overrides + [
            'customer_id' => $this->customer->id, 'contract_number' => 'SUP-1', 'description' => 'Support & hosting', 'currency' => 'USD',
            'billing_cycle' => 'monthly', 'starts_on' => '2026-01-15', 'term_months' => 12, 'auto_renew' => 1, 'escalation_percent' => 5, 'payment_terms_days' => 14,
            'lines' => [
                ['description' => 'Support retainer', 'revenue_account_id' => $this->ledgerAccount($this->company, '4000')->id, 'quantity' => 1, 'unit_price' => 200, 'tax_rate' => 15],
                ['description' => 'Hosting', 'revenue_account_id' => $this->ledgerAccount($this->company, '4100')->id, 'quantity' => 2, 'unit_price' => 25, 'tax_rate' => 0],
            ],
        ])->assertSessionHasNoErrors();

        return Subscription::latest('id')->first();
    }

    public function test_contract_is_set_up_with_its_term_and_first_invoice_date(): void
    {
        $contract = $this->contract();

        $this->assertSame(['active', '2026-01-15', '2027-01-14', 250.0], [$contract->status, $contract->next_invoice_on->toDateString(), $contract->ends_on->toDateString(), (float) $contract->recurring_amount]);
        $this->assertSame(250.0, $contract->monthlyValueInBase());
        $this->get(route('accounting.annuity-billing.index'))->assertOk()->assertSee('SUP-1')->assertSee('250.00');
    }

    public function test_billing_run_catches_up_missed_periods_once(): void
    {
        $contract = $this->contract();
        $this->goTo('2026-03-20');

        $this->get(route('accounting.annuity-billing.runs.preview'))->assertOk()->assertSee('3 periods catch-up');
        $this->post(route('accounting.annuity-billing.runs.store'), ['date' => '2026-03-20'])->assertSessionHasNoErrors();

        $invoices = SalesInvoice::where('subscription_id', $contract->id)->orderBy('invoice_number')->get();
        $this->assertSame(['SUP-1-202601', 'SUP-1-202602', 'SUP-1-202603'], $invoices->pluck('invoice_number')->all());
        $this->assertSame([250.0, 30.0, 280.0, 'draft', '2026-04-03'], [(float) $invoices[0]->subtotal, (float) $invoices[0]->tax_total, (float) $invoices[0]->total, $invoices[0]->status, $invoices[0]->due_date->toDateString()]);
        $this->assertStringContainsString('15 Feb 2026 – 14 Mar 2026', $invoices[1]->lines->first()->description);
        $this->assertSame('2026-04-15', $contract->fresh()->next_invoice_on->toDateString());

        // Running again the same day raises nothing.
        $this->post(route('accounting.annuity-billing.runs.store'), ['date' => '2026-03-20']);
        $this->assertSame(3, SalesInvoice::where('subscription_id', $contract->id)->count());
    }

    public function test_renewal_applies_escalation_from_the_new_term_only(): void
    {
        $contract = $this->contract();
        $contract->update(['next_invoice_on' => '2026-12-15']); // already billed to the end of November
        $this->goTo('2027-01-20');

        $this->post(route('accounting.annuity-billing.runs.store'), ['date' => '2027-01-20'])->assertSessionHasNoErrors();

        $december = SalesInvoice::where('invoice_number', 'SUP-1-202612')->first();
        $january = SalesInvoice::where('invoice_number', 'SUP-1-202701')->first();
        $this->assertSame(250.0, (float) $december->subtotal);
        $this->assertSame(262.5, (float) $january->subtotal); // 210 + 2 × 26.25
        $contract->refresh();
        $this->assertSame(['2028-01-14', 262.5], [$contract->ends_on->toDateString(), (float) $contract->recurring_amount]);
        $this->assertTrue($contract->events()->where('type', 'renewed')->exists());
    }

    public function test_contract_without_auto_renew_expires_at_the_end_of_its_term(): void
    {
        $contract = $this->contract(['auto_renew' => 0, 'contract_number' => 'SUP-2']);
        $contract->update(['next_invoice_on' => '2027-01-15']);
        $this->goTo('2027-02-01');

        $this->post(route('accounting.annuity-billing.runs.store'), ['date' => '2027-02-01']);

        $this->assertSame('expired', $contract->fresh()->status);
        $this->assertSame(0, SalesInvoice::where('subscription_id', $contract->id)->count());
    }

    public function test_invoices_can_be_posted_by_the_run(): void
    {
        $contract = $this->contract();
        $this->goTo('2026-01-15');

        $this->post(route('accounting.annuity-billing.runs.store'), ['date' => '2026-01-15', 'post' => 1])->assertSessionHasNoErrors();

        $invoice = SalesInvoice::where('subscription_id', $contract->id)->first();
        $this->assertSame('posted', $invoice->status);
        $this->assertTrue(JournalEntry::where('source_type', SalesInvoice::class)->where('source_id', $invoice->id)->where('status', 'posted')->exists());
    }

    public function test_pause_resume_and_cancel(): void
    {
        $contract = $this->contract();
        $this->post(route('accounting.annuity-billing.contracts.pause', $contract));
        $this->goTo('2026-05-02');
        $this->post(route('accounting.annuity-billing.runs.store'), ['date' => '2026-05-02']);
        $this->assertSame(0, SalesInvoice::count());

        // Resuming does not back-bill the paused months.
        $this->post(route('accounting.annuity-billing.contracts.resume', $contract));
        $this->assertSame('2026-05-02', $contract->fresh()->next_invoice_on->toDateString());

        $this->post(route('accounting.annuity-billing.contracts.cancel', $contract), ['reason' => 'Customer gave notice'])->assertSessionHasNoErrors();
        $this->post(route('accounting.annuity-billing.runs.store'), ['date' => '2026-05-02']);
        $this->assertSame(['cancelled', 0], [$contract->fresh()->status, SalesInvoice::count()]);
        $this->get(route('accounting.annuity-billing.contracts.show', $contract))->assertOk()->assertSee('Customer gave notice');
    }

    public function test_scheduled_command_bills_each_company(): void
    {
        $contract = $this->contract(['post_invoices' => 1]);
        auth()->forgetUser();
        $this->goTo('2026-02-16');

        $this->artisan('annuity:bill')->assertSuccessful();

        $this->assertSame(['posted', 'posted'], SalesInvoice::withoutGlobalScopes()->where('subscription_id', $contract->id)->orderBy('id')->pluck('status')->all());
    }
}

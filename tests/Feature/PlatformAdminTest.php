<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Platform\Plan;
use App\Models\Platform\PlatformInvoice;
use App\Models\Platform\Subscription;
use App\Models\User;
use App\Services\Platform\PlatformMetrics;
use App\Services\Platform\SubscriptionService;
use Database\Seeders\PlatformDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superuser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->superuser = User::create([
            'name' => 'Platform Admin', 'username' => 'root', 'email' => 'root@example.com',
            'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function company(string $name = 'Kopje Traders', string $country = 'ZW'): Company
    {
        return Company::create(['name' => $name, 'country' => $country, 'status' => true, 'onboarding_completed_at' => now()]);
    }

    private function plan(string $code): Plan
    {
        return Plan::where('code', $code)->firstOrFail();
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function liveMrr(): float
    {
        return round(Subscription::get()->sum(fn ($s) => $s->mrr()), 2);
    }

    public function test_only_superusers_can_open_the_platform_dashboard(): void
    {
        $company = $this->company();
        $admin = User::create(['name' => 'Tenant Admin', 'username' => 'tadmin', 'email' => 't@example.com', 'password' => 'secret-password', 'company_id' => $company->id, 'role' => 'admin']);

        $this->actingAs($admin)->get(route('system.platform.dashboard'))->assertForbidden();
        $this->actingAs($admin)->post(route('system.tenants.suspend', $company), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->superuser)->get(route('system.platform.dashboard'))->assertOk()->assertSee('Monthly recurring revenue');
    }

    public function test_superuser_lands_on_platform_dashboard_after_login(): void
    {
        $this->post('/login', ['login' => 'root', 'password' => 'secret-password'])
            ->assertRedirect(route('system.platform.dashboard'));
    }

    public function test_new_business_starts_on_a_trial_of_the_default_plan(): void
    {
        $sub = $this->company()->subscription;

        $this->assertSame('trialing', $sub->status);
        $this->assertSame('professional', $sub->plan->code);
        $this->assertSame('2026-06-29', $sub->trial_ends_at->toDateString());
        $this->assertSame(0.0, $sub->mrr());
    }

    public function test_plan_changes_promotions_and_cancellations_move_mrr_correctly(): void
    {
        $sub = $this->service()->activate($this->company()->subscription, $this->plan('professional'), 'monthly', 'paynow');
        $this->assertSame(39.0, $sub->mrr());

        $this->actingAs($this->superuser)->post(route('system.tenants.plan', $sub->company_id), [
            'platform_plan_id' => $this->plan('business')->id, 'billing_cycle' => 'annual', 'note' => 'Upgrade',
        ])->assertSessionHasNoErrors();
        $sub->refresh();
        $this->assertSame(65.83, $sub->mrr()); // 790 / 12
        $this->assertSame('expansion', $sub->events()->first()->movement);

        $this->post(route('system.tenants.promotion', $sub->company_id), [
            'platform_plan_id' => $this->plan('business')->id, 'months' => 3, 'note' => 'Partner programme',
        ])->assertSessionHasNoErrors();
        $sub->refresh();
        $this->assertTrue($sub->is_promotional);
        $this->assertSame(0.0, $sub->mrr());
        $this->assertSame('2026-09-15', $sub->promo_ends_at->toDateString());

        $other = $this->service()->activate($this->company('Msasa Foods', 'ZA')->subscription, $this->plan('essential'));
        $this->post(route('system.tenants.cancel', $other->company_id), ['reason' => 'Too expensive'])->assertSessionHasNoErrors();
        $this->assertSame('churn', $other->events()->first()->movement);

        $metrics = new PlatformMetrics(now());
        $this->assertSame($this->liveMrr(), $metrics->mrr());
        $this->assertSame(1, $metrics->cancellations()['count']);
        $this->assertSame(19.0, $metrics->cancellations()['lost_mrr']);
        $this->assertSame(39.0 + 19.0, $metrics->movements()['new']);
    }

    public function test_extend_trial_from_today_when_expired(): void
    {
        $company = $this->company();
        Carbon::setTestNow('2026-07-10 09:00:00'); // trial ended 29 Jun

        $this->actingAs($this->superuser)->post(route('system.tenants.trial', $company), ['days' => 10])->assertSessionHasNoErrors();
        $this->assertSame('2026-07-20', $company->subscription->fresh()->trial_ends_at->toDateString());

        $this->service()->activate($company->subscription->fresh());
        $this->post(route('system.tenants.trial', $company), ['days' => 10])->assertSessionHasErrors('days');
    }

    public function test_suspension_blocks_signed_in_users_and_reactivation_restores_access(): void
    {
        $company = $this->company();
        $sub = $this->service()->activate($company->subscription, $this->plan('essential'));
        $tenantUser = User::create(['name' => 'Clerk', 'username' => 'clerk', 'email' => 'c@example.com', 'password' => 'secret-password', 'company_id' => $company->id, 'role' => 'accountant']);

        $this->actingAs($this->superuser)->post(route('system.tenants.suspend', $company), ['reason' => 'Unpaid'])->assertSessionHasNoErrors();
        $this->assertFalse($company->fresh()->status);
        $this->assertSame(0.0, (new PlatformMetrics(now()))->mrr());

        $this->actingAs($tenantUser)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($this->superuser)->post(route('system.tenants.reactivate', $company))->assertSessionHasNoErrors();
        $this->assertTrue($company->fresh()->status);
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertSame(19.0, (new PlatformMetrics(now()))->mrr());
    }

    public function test_billing_metrics_by_country_provider_and_failures(): void
    {
        $zw = $this->service()->activate($this->company('Baobab Agro', 'ZW')->subscription, $this->plan('professional'), 'monthly', 'ecocash');
        $za = $this->service()->activate($this->company('Summit Motors', 'ZA')->subscription, $this->plan('business'), 'monthly', 'stripe');
        $pay = function ($sub, $amount, $status, $provider) {
            $invoice = PlatformInvoice::create([
                'company_id' => $sub->company_id, 'platform_subscription_id' => $sub->id, 'number' => 'T-'.uniqid(),
                'amount' => $amount, 'status' => $status === 'succeeded' ? 'paid' : 'pending', 'issued_at' => now(),
            ]);
            \App\Models\Platform\Payment::create([
                'platform_invoice_id' => $invoice->id, 'company_id' => $sub->company_id, 'provider' => $provider,
                'transaction_reference' => 'T-'.uniqid(), 'amount' => $amount, 'status' => $status,
                'paid_at' => $status === 'succeeded' ? now() : null, 'failure_reason' => $status === 'failed' ? 'Card declined' : null,
            ]);
        };
        $pay($zw, 39, 'succeeded', 'ecocash');
        $pay($za, 79, 'succeeded', 'stripe');
        $pay($za, 79, 'failed', 'stripe');

        $metrics = new PlatformMetrics(now());
        $this->assertSame(['South Africa' => 79.0, 'Zimbabwe' => 39.0], $metrics->revenueByCountry()->pluck('amount', 'label')->all());
        $this->assertSame(['Stripe' => 79.0, 'EcoCash' => 39.0], $metrics->revenueByProvider()->pluck('amount', 'label')->all());
        $this->assertSame(1, $metrics->failedPayments()['count']);

        $this->actingAs($this->superuser)->get(route('system.tenants.show', $za->company_id))
            ->assertOk()->assertSee('Billing history')->assertSee('Card declined');
    }

    public function test_conversion_rate_ignores_businesses_still_on_trial(): void
    {
        Carbon::setTestNow('2026-06-01 09:00:00');
        $converted = $this->company('A Traders');
        $lapsed = $this->company('B Traders');
        Carbon::setTestNow('2026-06-15 12:00:00');
        $this->service()->activate($converted->subscription);
        $this->company('C Traders'); // registered today, still on trial

        $cohort = (new PlatformMetrics(now()))->registrationHistory(1)->first();
        $this->assertSame([3, 1, 1, 1, 50.0], [$cohort['registered'], $cohort['converted'], $cohort['lapsed'], $cohort['pending'], $cohort['rate']]);
        $this->assertNotNull($lapsed);
    }

    public function test_dashboard_and_tenant_pages_render_with_a_year_of_demo_data(): void
    {
        Carbon::setTestNow();
        $this->seed(PlatformDemoSeeder::class);

        $this->assertSame($this->liveMrr(), (new PlatformMetrics(now()))->mrr());
        $this->assertGreaterThan(100, Company::count());

        $this->actingAs($this->superuser);
        $this->get(route('system.platform.dashboard'))->assertOk()->assertSee('MRR growth')->assertSee('Revenue by country')->assertSee('Revenue by payment provider');
        $this->get(route('system.platform.dashboard', ['month' => now()->subMonths(3)->format('Y-m')]))->assertOk();
        $this->get(route('system.tenants.index', ['status' => 'suspended']))->assertOk();
        $this->get(route('system.tenants.index', ['status' => 'trial_expired', 'q' => 'a']))->assertOk();
        $this->get(route('system.tenants.show', Subscription::where('status', 'active')->value('company_id')))->assertOk()->assertSee('Change plan');
    }
}

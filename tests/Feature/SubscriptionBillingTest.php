<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Platform\Coupon;
use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use App\Models\Platform\Plan;
use App\Models\Platform\PlatformInvoice;
use App\Models\User;
use App\Notifications\SubscriptionPaymentReminder;
use App\Services\Billing\Gateways\PaynowGateway;
use App\Services\Platform\PlatformMetrics;
use App\Services\Platform\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-01 09:00:00');
        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sub()
    {
        return $this->company->fresh()->subscription;
    }

    private function onPlan(string $code): void
    {
        app(SubscriptionService::class)->changePlan($this->sub(), Plan::where('code', $code)->first(), 'monthly');
    }

    private function goTo(string $when): void
    {
        Carbon::setTestNow(Carbon::parse($when));
    }

    /** Checkout + sandbox success; returns the paid invoice. */
    private function subscribe(string $plan = 'professional', string $cycle = 'monthly', ?string $coupon = null): PlatformInvoice
    {
        $this->post(route('billing.checkout'), ['plan' => $plan, 'billing_cycle' => $cycle, 'coupon' => $coupon])->assertSessionHasNoErrors();
        $invoice = PlatformInvoice::where('company_id', $this->company->id)->latest('id')->first();
        $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'sandbox']);
        $payment = Payment::where('platform_invoice_id', $invoice->id)->latest('id')->first();
        $this->post(route('billing.sandbox.complete', $payment), ['outcome' => 'success'])->assertRedirect(route('billing.payments.return', $payment));
        $this->get(route('billing.payments.return', $payment));

        return $invoice->fresh();
    }

    // ---- trial & locking -----------------------------------------------------

    public function test_registration_starts_a_14_day_trial_on_the_chosen_plan(): void
    {
        auth()->logout();
        $this->post('/register', ['name' => 'Tendai', 'email' => 'tendai@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'plan' => 'essential']);

        $sub = User::where('email', 'tendai@example.com')->first()->company->subscription;
        $this->assertSame(['trialing', 'essential', '2026-06-15'], [$sub->status, $sub->plan->code, $sub->trial_ends_at->toDateString()]);
        $this->assertNotNull($sub->company->slug);
    }

    public function test_workspace_locks_when_the_trial_ends(): void
    {
        $accountant = $this->ledgerUser($this->company, 'accountant');
        $this->get(route('dashboard'))->assertOk();

        $this->goTo('2026-06-12 09:00');
        $this->get(route('dashboard'))->assertOk()->assertSee('Your free trial ends');

        $this->goTo('2026-06-16 09:00');
        $this->get(route('accounting.ar.invoices.index'))->assertRedirect(route('billing.index'));
        $this->get(route('billing.index'))->assertOk()->assertSee('free trial has ended');
        $this->actingAs($accountant)->get(route('dashboard'))->assertRedirect(route('billing.locked'));
        $this->actingAs($accountant)->get(route('billing.locked'))->assertOk()->assertSee('Company Admin');
        $this->actingAs($accountant)->get(route('billing.index'))->assertForbidden();
    }

    // ---- checkout & payment --------------------------------------------------

    public function test_paying_activates_the_subscription_and_unlocks_the_workspace(): void
    {
        Coupon::create(['code' => 'LAUNCH20', 'discount_type' => 'percent', 'discount_value' => 20, 'duration' => 'once', 'is_active' => true]);
        $this->goTo('2026-06-16 09:00');

        $invoice = $this->subscribe('professional', 'monthly', 'launch20');

        $this->assertSame(['paid', 31.2, 7.8], [$invoice->status, (float) $invoice->amount, (float) $invoice->discount_total]);
        $sub = $this->sub();
        $this->assertSame(['active', 'professional', '2026-07-16'], [$sub->status, $sub->plan->code, $sub->current_period_ends_at->toDateString()]);
        $this->assertSame(39.0, (new PlatformMetrics(now()))->mrr()); // one-off coupon does not change MRR
        $this->assertSame(1, Coupon::first()->times_redeemed);
        $this->get(route('accounting.ar.invoices.index'))->assertOk();
    }

    public function test_failed_payment_leaves_invoice_open_until_a_retry_succeeds(): void
    {
        $this->goTo('2026-06-16 09:00');
        $this->post(route('billing.checkout'), ['plan' => 'essential', 'billing_cycle' => 'annual']);
        $invoice = PlatformInvoice::latest('id')->first();
        $this->assertSame(190.0, (float) $invoice->amount);

        $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'sandbox']);
        $payment = Payment::latest('id')->first();
        $this->post(route('billing.sandbox.complete', $payment), ['outcome' => 'failure']);
        $this->get(route('billing.payments.return', $payment))->assertSessionHas('error');

        $this->assertSame(['failed', 'pending'], [$payment->fresh()->status, $invoice->fresh()->status]);
        $this->get(route('dashboard'))->assertRedirect(route('billing.index'));

        $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'sandbox']);
        $retry = Payment::latest('id')->first();
        $this->post(route('billing.sandbox.complete', $retry), ['outcome' => 'success']);
        $this->get(route('billing.payments.return', $retry));
        $this->assertSame('active', $this->sub()->status);
        $this->assertSame('2027-06-16', $this->sub()->current_period_ends_at->toDateString());
    }

    public function test_full_discount_coupon_activates_without_payment(): void
    {
        Coupon::create(['code' => 'FREEMONTH', 'discount_type' => 'percent', 'discount_value' => 100, 'duration' => 'once', 'is_active' => true]);

        $this->post(route('billing.checkout'), ['plan' => 'business', 'billing_cycle' => 'monthly', 'coupon' => 'FREEMONTH'])->assertRedirect(route('billing.index'));
        $this->assertSame('active', $this->sub()->status);
    }

    public function test_invalid_coupons_and_enterprise_self_checkout_are_rejected(): void
    {
        Coupon::create(['code' => 'OLD', 'discount_type' => 'fixed', 'discount_value' => 5, 'duration' => 'once', 'is_active' => true, 'expires_at' => '2026-05-01']);

        $this->post(route('billing.checkout'), ['plan' => 'professional', 'billing_cycle' => 'monthly', 'coupon' => 'OLD'])->assertSessionHasErrors('coupon');
        $this->post(route('billing.checkout'), ['plan' => 'professional', 'billing_cycle' => 'monthly', 'coupon' => 'NOPE'])->assertSessionHasErrors('coupon');
        $this->post(route('billing.checkout'), ['plan' => 'enterprise', 'billing_cycle' => 'monthly'])->assertSessionHasErrors('plan');
    }

    public function test_tenants_cannot_open_each_others_invoices(): void
    {
        $this->post(route('billing.checkout'), ['plan' => 'essential', 'billing_cycle' => 'monthly']);
        $invoice = PlatformInvoice::latest('id')->first();

        $other = $this->createLedgerCompany('Other');
        $this->actingAs($this->ledgerUser($other, 'admin'))->get(route('billing.invoices.show', $invoice))->assertNotFound();
    }

    // ---- providers & webhooks ------------------------------------------------

    public function test_paynow_payment_is_confirmed_by_a_verified_result_callback_once(): void
    {
        $key = 'test-integration-key';
        PaymentGateway::where('provider', 'paynow')->first()->update(['status' => true, 'configuration' => ['integration_id' => '1234', 'integration_key' => $key, 'auth_email' => 'me@example.com']]);

        $reply = ['status' => 'Ok', 'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/9', 'pollurl' => 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=9'];
        $reply['hash'] = PaynowGateway::hash($reply, $key);
        Http::fake([PaynowGateway::INITIATE_URL => Http::response(http_build_query($reply))]);

        $this->post(route('billing.checkout'), ['plan' => 'professional', 'billing_cycle' => 'monthly']);
        $invoice = PlatformInvoice::latest('id')->first();
        $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'paynow'])->assertRedirect($reply['browserurl']);
        $payment = Payment::latest('id')->first();
        Http::assertSent(fn ($request) => $request['reference'] === $payment->transaction_reference && $request['hash'] === PaynowGateway::hash(collect($request->data())->except('hash')->all(), $key));

        $result = ['reference' => $payment->transaction_reference, 'paynowreference' => '777', 'amount' => '39.00', 'status' => 'Paid', 'pollurl' => $reply['pollurl']];
        $forged = $result + ['hash' => 'BAD'];
        $this->call('POST', route('billing.webhooks', 'paynow'), [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($forged))->assertStatus(400);
        $this->assertSame('pending', $payment->fresh()->status);

        $result['hash'] = PaynowGateway::hash($result, $key);
        $body = http_build_query($result);
        $this->call('POST', route('billing.webhooks', 'paynow'), [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], $body)->assertOk();
        $this->call('POST', route('billing.webhooks', 'paynow'), [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], $body)->assertOk();

        $this->assertSame(['succeeded', 'paid', 'active'], [$payment->fresh()->status, $invoice->fresh()->status, $this->sub()->status]);
        $this->assertSame(1, DB::table('platform_subscription_events')->where('type', 'activated')->count());
    }

    public function test_stripe_webhook_requires_a_valid_signature(): void
    {
        PaymentGateway::where('provider', 'stripe')->first()->update(['status' => true, 'configuration' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test']]);
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);

        $this->post(route('billing.checkout'), ['plan' => 'business', 'billing_cycle' => 'monthly']);
        $invoice = PlatformInvoice::latest('id')->first();
        $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'stripe'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $payment = Payment::latest('id')->first();

        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_test_1', 'client_reference_id' => $payment->transaction_reference, 'payment_status' => 'paid', 'amount_total' => 7900,
        ]]]);
        $sign = fn ($secret) => 't='.time().',v1='.hash_hmac('sha256', time().'.'.$payload, $secret);

        $this->call('POST', route('billing.webhooks', 'stripe'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sign('wrong')], $payload)->assertStatus(400);
        $this->assertSame('pending', $payment->fresh()->status);

        $this->call('POST', route('billing.webhooks', 'stripe'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sign('whsec_test')], $payload)->assertOk();
        $this->assertSame(['succeeded', 'active', 'business'], [$payment->fresh()->status, $this->sub()->status, $this->sub()->plan->code]);
    }

    // ---- renewal, past due, grace, suspension ---------------------------------

    public function test_renewal_cycle_reminds_then_suspends_unpaid_accounts_and_payment_restores_them(): void
    {
        Notification::fake();
        $this->subscribe('essential'); // active until 2026-07-01

        $this->goTo('2026-06-25 09:00');
        $this->artisan('billing:run')->assertSuccessful();
        $renewal = PlatformInvoice::where('purpose', 'renewal')->firstOrFail();
        $this->assertSame(['pending', 19.0, '2026-07-01'], [$renewal->status, (float) $renewal->amount, $renewal->period_start->toDateString()]);
        Notification::assertSentTo($this->admin, SubscriptionPaymentReminder::class);

        $this->goTo('2026-07-02 09:00');
        $this->artisan('billing:run');
        $this->assertSame('past_due', $this->sub()->status);
        $this->get(route('dashboard'))->assertOk()->assertSee('payment is overdue'); // grace period: still open

        $this->goTo('2026-07-10 09:00');
        $this->artisan('billing:run');
        $sub = $this->sub();
        $this->assertSame(['suspended', 'billing'], [$sub->status, $sub->suspension_type]);
        $this->assertTrue($this->company->fresh()->status); // can still sign in to pay
        $this->get(route('dashboard'))->assertRedirect(route('billing.index'));
        $this->assertSame(0.0, (new PlatformMetrics(now()))->mrr());

        $superuser = User::create(['name' => 'Root', 'username' => 'root', 'email' => 'root@example.com', 'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => true]);
        $this->actingAs($superuser)->post(route('system.tenants.payments.record', [$this->company, $renewal]), ['provider' => 'bank_transfer', 'reference' => 'CBZ-55'])->assertSessionHasNoErrors();

        $sub = $this->sub();
        $this->assertSame(['active', '2026-08-10'], [$sub->status, $sub->current_period_ends_at->toDateString()]);
        $this->assertSame(19.0, (new PlatformMetrics(now()))->mrr());
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    }

    // ---- features & limits ----------------------------------------------------

    public function test_plan_features_gate_screens(): void
    {
        $this->onPlan('essential');
        $this->get(route('accounting.banking.index'))->assertRedirect(route('billing.index'))->assertSessionHas('warning', fn ($m) => str_contains($m, 'Professional'));
        $this->get(route('accounting.gl.cash-flow'))->assertRedirect(route('billing.index'));
        $this->get(route('accounting.gl.trial-balance'))->assertOk();

        $this->onPlan('professional');
        $this->get(route('accounting.banking.index'))->assertOk();
    }

    public function test_plan_limits_users_branches_and_advanced_roles(): void
    {
        $this->onPlan('essential'); // 2 users, 1 branch, no advanced permissions
        $user = fn ($name, $role = 'accountant') => ['name' => $name, 'username' => strtolower($name), 'email' => strtolower($name).'@example.com', 'role' => $role, 'password' => 'password123', 'password_confirmation' => 'password123'];

        $this->post(route('company.team.store'), $user('Fiona', 'finance_manager'))->assertSessionHasErrors('role');
        $this->post(route('company.team.store'), $user('Clerk'))->assertSessionHasNoErrors();
        $this->post(route('company.team.store'), $user('Extra'))->assertSessionHasErrors('name');

        $this->post(route('accounting.inventory.warehouses.store'), ['code' => 'MAIN', 'name' => 'Main', 'status' => 1])->assertSessionHasNoErrors();
        $this->post(route('accounting.inventory.warehouses.store'), ['code' => 'BR2', 'name' => 'Branch', 'status' => 1])->assertSessionHasErrors('code');

        $this->get(route('billing.index'))->assertSee('2 of 2');
    }

    // ---- platform administration ---------------------------------------------

    public function test_platform_admin_manages_coupons_plans_and_encrypted_gateway_settings(): void
    {
        $superuser = User::create(['name' => 'Root', 'username' => 'root', 'email' => 'root@example.com', 'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => true]);
        $this->actingAs($superuser);

        $this->post(route('system.coupons.store'), ['code' => 'partner', 'discount_type' => 'fixed', 'discount_value' => 10, 'duration' => 'forever'])->assertSessionHasNoErrors();
        $this->assertSame('PARTNER', Coupon::first()->code);

        $gateway = PaymentGateway::where('provider', 'paynow')->first();
        $this->put(route('system.gateways.update', $gateway), ['display_name' => 'Paynow', 'mode' => 'live', 'status' => 1, 'config' => ['integration_id' => '99', 'integration_key' => 'super-secret']])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('super-secret', DB::table('platform_payment_gateways')->where('id', $gateway->id)->value('configuration'));
        $this->put(route('system.gateways.update', $gateway), ['display_name' => 'Paynow', 'mode' => 'live', 'status' => 1, 'config' => ['integration_id' => '99', 'integration_key' => '']]);
        $this->assertSame('super-secret', $gateway->fresh()->config('integration_key')); // blank secret keeps the saved one

        $plan = Plan::where('code', 'essential')->first();
        $this->put(route('system.plans.update', $plan), ['monthly_price' => 21, 'annual_price' => 210, 'is_active' => 1, 'limits' => ['users' => 3, 'branches' => 1, 'companies' => 1], 'features' => ['general_ledger', 'invoicing']])->assertSessionHasNoErrors();
        $this->assertSame(3, $plan->fresh()->load('features')->limit('users'));

        foreach (['system.plans.index', 'system.coupons.index', 'system.gateways.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('system.tenants.show', $this->company))->assertOk()->assertSee('Custom price and limits');
    }
}

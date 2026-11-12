<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use App\Models\Platform\PlatformInvoice;
use App\Models\User;
use App\Services\Billing\Gateways\PesepayClientFactory;
use Codevirtus\Payments\ErrorResponse;
use Codevirtus\Payments\Pesepay;
use Codevirtus\Payments\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class PesepayGatewayTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private const ENCRYPTION_KEY = '0123456789abcdef0123456789abcdef';

    private Company $company;
    private User $admin;
    private FakePesepayApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-01 09:00:00');
        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->actingAs($this->admin);

        PaymentGateway::where('provider', 'pesepay')->firstOrFail()->update([
            'status' => true, 'mode' => 'test',
            'configuration' => ['integration_key' => 'int-key-123', 'encryption_key' => self::ENCRYPTION_KEY],
        ]);

        $this->api = new FakePesepayApi();
        $api = $this->api;
        $this->app->instance(PesepayClientFactory::class, new class($api) extends PesepayClientFactory {
            public function __construct(private FakePesepayApi $api) {}

            public function make(string $integrationKey, string $encryptionKey, bool $sandbox): Pesepay
            {
                $this->api->made[] = compact('integrationKey', 'encryptionKey', 'sandbox');

                return new FakePesepay($this->api, $integrationKey, $encryptionKey, $sandbox);
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Checkout Professional monthly ($39) and start a Pesepay payment. */
    private function startPayment(): array
    {
        $this->post(route('billing.checkout'), ['plan' => 'professional', 'billing_cycle' => 'monthly']);
        $invoice = PlatformInvoice::latest('id')->firstOrFail();
        $response = $this->post(route('billing.invoices.pay', $invoice), ['provider' => 'pesepay']);

        return [$invoice, Payment::latest('id')->firstOrFail(), $response];
    }

    private function sendCallback(array $body)
    {
        return $this->call('POST', route('billing.webhooks', 'pesepay'), [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    public function test_payment_redirects_to_pesepay_checkout_with_the_invoice_details(): void
    {
        [$invoice, $payment, $response] = $this->startPayment();

        $response->assertRedirect('https://sandbox.pesepay.test/checkout/PSP-1');
        $this->assertSame(['integrationKey' => 'int-key-123', 'encryptionKey' => self::ENCRYPTION_KEY, 'sandbox' => true], $this->api->made[0]);

        $sent = $this->api->transaction;
        $this->assertEquals(39.0, $sent->amountDetails->amount);
        $this->assertSame('USD', $sent->amountDetails->currencyCode);
        $this->assertSame($payment->transaction_reference, $sent->merchantReference);
        $this->assertStringContainsString($invoice->number, $sent->reasonForPayment);
        $this->assertSame(route('billing.webhooks', 'pesepay'), $sent->resultUrl);
        $this->assertSame(route('billing.payments.return', $payment), $sent->returnUrl);

        $this->assertSame('PSP-1', $payment->provider_reference);
        $this->assertSame('https://sandbox.pesepay.test/poll/PSP-1', $payment->poll_url);
    }

    public function test_live_mode_uses_the_production_api(): void
    {
        PaymentGateway::where('provider', 'pesepay')->update(['mode' => 'live']);
        $this->startPayment();

        $this->assertFalse($this->api->made[0]['sandbox']);
    }

    public function test_callback_is_only_trusted_after_pesepay_confirms_it(): void
    {
        [$invoice, $payment] = $this->startPayment();

        // Forged callback claiming success while Pesepay still reports the payment pending.
        $this->sendCallback(['referenceNumber' => 'PSP-1', 'transactionStatus' => 'SUCCESS'])->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);

        // A reference Pesepay never gave us is rejected outright.
        $this->sendCallback(['referenceNumber' => 'PSP-404', 'transactionStatus' => 'SUCCESS'])->assertStatus(400);

        // Customer pays: Pesepay now confirms. The callback is delivered twice (retries).
        $this->api->status = 'SUCCESS';
        $this->sendCallback(['referenceNumber' => 'PSP-1', 'transactionStatus' => 'SUCCESS'])->assertOk();
        $this->sendCallback(['referenceNumber' => 'PSP-1', 'transactionStatus' => 'SUCCESS'])->assertOk();

        $this->assertSame(['succeeded', 'paid', 'active'], [$payment->fresh()->status, $invoice->fresh()->status, $this->company->fresh()->subscription->status]);
        $this->assertSame(1, DB::table('platform_subscription_events')->where('type', 'activated')->count());
        $this->assertSame(['https://sandbox.pesepay.test/poll/PSP-1'], array_unique($this->api->polled));
    }

    public function test_encrypted_callback_payload_is_understood(): void
    {
        [, $payment] = $this->startPayment();
        $this->api->status = 'SUCCESS';

        $iv = substr(self::ENCRYPTION_KEY, 0, 16);
        $payload = openssl_encrypt(json_encode(['referenceNumber' => 'PSP-1']), 'AES-256-CBC', self::ENCRYPTION_KEY, 0, $iv);
        $this->sendCallback(['payload' => $payload])->assertOk();

        $this->assertSame('succeeded', $payment->fresh()->status);
    }

    public function test_return_page_verifies_and_reports_a_cancelled_payment(): void
    {
        [$invoice, $payment] = $this->startPayment();
        $this->api->status = 'CANCELLED';

        $this->get(route('billing.payments.return', $payment))
            ->assertRedirect(route('billing.invoices.show', $invoice))
            ->assertSessionHas('error');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_short_payment_or_wrong_currency_is_not_accepted(): void
    {
        [, $payment] = $this->startPayment();
        $this->api->status = 'SUCCESS';
        $this->api->amount = 3.90;
        $this->get(route('billing.payments.return', $payment));
        $this->assertStringContainsString('does not match', $payment->fresh()->failure_reason);

        [, $second] = $this->startPayment();
        $this->api->amount = 39.00;
        $this->api->currency = 'ZWG';
        $this->get(route('billing.payments.return', $second));
        $this->assertSame('failed', $second->fresh()->status);
        $this->assertStringContainsString('ZWG', $second->fresh()->failure_reason);
    }

    public function test_initiation_error_is_shown_to_the_customer(): void
    {
        $this->api->initiateError = 'Invalid integration key';
        [, $payment, $response] = $this->startPayment();

        $response->assertSessionHasErrors(['payment' => 'Pesepay could not start the payment: Invalid integration key.']);
        $this->assertSame('failed', $payment->status);
    }

    public function test_admin_cannot_save_an_encryption_key_of_the_wrong_length(): void
    {
        $root = User::create(['name' => 'Root', 'username' => 'root', 'email' => 'root@example.com', 'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => true]);
        $gateway = PaymentGateway::where('provider', 'pesepay')->first();

        $this->actingAs($root)->put(route('system.gateways.update', $gateway), [
            'display_name' => 'Pesepay', 'mode' => 'live', 'status' => 1, 'config' => ['integration_key' => 'new-key', 'encryption_key' => 'too-short'],
        ])->assertSessionHasErrors('config.encryption_key');
        $this->assertSame('test', $gateway->fresh()->mode);

        $this->put(route('system.gateways.update', $gateway), [
            'display_name' => 'Pesepay', 'mode' => 'live', 'status' => 1, 'config' => ['integration_key' => 'new-key', 'encryption_key' => ''],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['new-key', self::ENCRYPTION_KEY], [$gateway->fresh()->config('integration_key'), $gateway->fresh()->config('encryption_key')]);
    }
}

/** Shared state of the simulated Pesepay API. */
class FakePesepayApi
{
    public array $made = [];
    public array $polled = [];
    public $transaction;
    public string $status = 'PENDING';
    public float $amount = 39.00;
    public string $currency = 'USD';
    public ?string $initiateError = null;
    public int $counter = 0;
    public array $merchantRefs = [];
}

class FakePesepay extends Pesepay
{
    public function __construct(private FakePesepayApi $api, $integrationKey, $encryptionKey, $isSandbox)
    {
        parent::__construct($integrationKey, $encryptionKey, $isSandbox);
    }

    public function initiateTransaction($transaction)
    {
        $transaction->resultUrl = $this->resultUrl;
        $transaction->returnUrl = $this->returnUrl;
        $this->api->transaction = $transaction;
        if ($this->api->initiateError) {
            return new ErrorResponse($this->api->initiateError);
        }

        $ref = 'PSP-'.(++$this->api->counter);
        $this->api->merchantRefs[$ref] = $transaction->merchantReference;

        return new Response($ref, 'https://sandbox.pesepay.test/poll/'.$ref, 'https://sandbox.pesepay.test/checkout/'.$ref, false, ['referenceNumber' => $ref]);
    }

    public function pollTransaction($pollUrl)
    {
        $this->api->polled[] = $pollUrl;
        $ref = basename(parse_url($pollUrl, PHP_URL_PATH));

        return new Response($ref, $pollUrl, null, $this->api->status === 'SUCCESS', [
            'referenceNumber' => $ref,
            'merchantReference' => $this->api->merchantRefs[$ref] ?? null,
            'transactionStatus' => $this->api->status,
            'amountDetails' => ['amount' => $this->api->amount, 'currencyCode' => $this->api->currency],
        ]);
    }

    public function checkPayment($referenceNumber)
    {
        return $this->pollTransaction('https://sandbox.pesepay.test/poll/'.$referenceNumber);
    }
}

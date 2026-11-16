<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Platform\FiscalDevice;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\EinvoiceDocument;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Services\Fiscal\FdmsCli;
use App\Services\Fiscal\FdmsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

/** ZIMRA FDMS set-up by superusers and invoice fiscalisation, against a fake zimra-fdms CLI. */
class FiscalisationTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected User $superuser;
    private FakeFdmsCli $cli;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-10 09:00:00');
        Notification::fake();
        config(['zimra.profiles_path' => storage_path('framework/testing/zimra-'.uniqid())]);
        $this->cli = new FakeFdmsCli;
        $this->app->instance(FdmsCli::class, $this->cli);

        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->superuser = User::create(['name' => 'Platform Admin', 'username' => 'root', 'email' => 'root@example.com',
            'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(config('zimra.profiles_path'));
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function settings(array $overrides = []): array
    {
        return $overrides + [
            'environment' => 'test', 'device_id' => 12345, 'serial_number' => 'ACC263-01', 'model_name' => 'Server', 'model_version' => 'v1',
            'activation_key' => 'ABCD1234', 'zero_rate_lines_as' => 'zero_rated', 'payment_type' => 'Credit', 'auto_submit' => 1, 'auto_close_day' => 1,
        ];
    }

    /** Saves, registers and enables the device. */
    private function activate(): FiscalDevice
    {
        $this->actingAs($this->superuser)->put(route('system.fiscalisation.update', $this->company), $this->settings())->assertSessionHasNoErrors();
        $this->post(route('system.fiscalisation.register', $this->company))->assertSessionHas('success');
        $device = FiscalDevice::where('company_id', $this->company->id)->firstOrFail();
        $this->put(route('system.fiscalisation.update', $this->company), $this->settings([
            'standard_tax_id' => $device->standard_tax_id, 'zero_rated_tax_id' => $device->zero_rated_tax_id, 'exempt_tax_id' => $device->exempt_tax_id, 'enabled' => 1,
        ]))->assertSessionHasNoErrors();

        return $device->fresh();
    }

    private function invoice(array $lines = [[100, 15]]): SalesInvoice
    {
        $this->actingAs($this->admin);
        $customer = Customer::create(['customer_number' => 'C'.uniqid(), 'name' => 'Client Ltd', 'email' => 'client@example.com', 'credit_limit' => 0, 'status' => true]);
        $this->post(route('accounting.ar.invoices.store'), [
            'customer_id' => $customer->id, 'invoice_number' => $number = 'INV-'.uniqid(), 'invoice_date' => '2026-06-10', 'due_date' => '2026-06-30', 'currency' => 'USD',
            'lines' => array_map(fn ($l) => ['revenue_account_id' => $this->ledgerAccount($this->company, '4000')->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => $l[0], 'tax_rate' => $l[1]], $lines),
        ])->assertSessionHasNoErrors();

        return SalesInvoice::where('invoice_number', $number)->firstOrFail();
    }

    public function test_only_superusers_manage_fiscalisation(): void
    {
        $this->actingAs($this->admin)->get(route('system.fiscalisation.index'))->assertForbidden();
        $this->actingAs($this->admin)->put(route('system.fiscalisation.update', $this->company), $this->settings())->assertForbidden();

        $this->actingAs($this->superuser)->get(route('system.fiscalisation.index'))->assertOk()->assertSee($this->company->name)->assertSee('Not set up');
        $this->get(route('system.fiscalisation.edit', $this->company))->assertOk()->assertSee('Device settings');
    }

    public function test_device_is_registered_and_its_zimra_details_saved(): void
    {
        $this->actingAs($this->superuser)->put(route('system.fiscalisation.update', $this->company), $this->settings(['enabled' => 1]))
            ->assertSessionHas('warning'); // cannot enable before registering
        $device = FiscalDevice::where('company_id', $this->company->id)->firstOrFail();
        $this->assertFalse($device->enabled);
        $this->assertSame('ABCD1234', $device->activation_key);
        $this->assertStringNotContainsString('ABCD1234', (string) DB::table('fiscal_devices')->value('activation_key')); // encrypted at rest

        $this->post(route('system.fiscalisation.register', $this->company))->assertSessionHas('success');
        $register = $this->cli->calls[0];
        $this->assertSame(['register', '--device-id', '12345', '--serial', 'ACC263-01', '--activation-key', 'ABCD1234'], array_slice($register['args'], 0, 7));
        $this->assertContains('test', $register['args']);
        $this->assertStringEndsWith('/'.$this->company->id, $register['dir']);

        $device->refresh();
        $this->assertNotNull($device->registered_at);
        $this->assertNull($device->activation_key); // single-use: forgotten after registration
        $this->assertSame(['Acme Trading (Pvt) Ltd', '2000123456', 1, 2, 3], [$device->taxpayer_name, $device->taxpayer_tin, $device->standard_tax_id, $device->zero_rated_tax_id, $device->exempt_tax_id]);

        $this->get(route('system.fiscalisation.edit', $this->company))->assertOk()->assertSee('Acme Trading (Pvt) Ltd')->assertSee('ID 1 · 15.5% · Standard rated');
    }

    public function test_posting_an_invoice_fiscalises_it_once_and_shows_the_signed_details(): void
    {
        $this->activate();
        $invoice = $this->invoice([[100, 15.5], [50, 0]]);

        $this->actingAs($this->admin)->post(route('accounting.ar.invoices.post', $invoice))->assertSessionHas('success', fn ($m) => str_contains($m, 'Fiscalised with ZIMRA'));

        // The fiscal day opened by itself before the first receipt.
        $this->assertSame([['day', 'open'], ['submit']], array_map(fn ($c) => array_slice($c['args'], 0, $c['args'][0] === 'day' ? 2 : 1), array_slice($this->cli->calls, -2)));
        $receipt = $this->cli->lastReceipt;
        $this->assertSame([1, 15.5], [$receipt['lines'][0]['taxId'], $receipt['lines'][0]['taxPercent']]);
        $this->assertSame([2, 0], [$receipt['lines'][1]['taxId'], $receipt['lines'][1]['taxPercent']]);
        $this->assertSame(['Credit', 165.5], [$receipt['payments'][0]['moneyType'], $receipt['payments'][0]['amount']]);
        $this->assertSame('FiscalInvoice', $receipt['receiptType']);

        $doc = EinvoiceDocument::withoutGlobalScopes()->where('document_number', $invoice->invoice_number)->firstOrFail();
        $this->assertSame(['cleared', 'R-1001', 'ABCD-EF01-2345-6789', 1, '2000123456'], [$doc->status, $doc->authority_reference, $doc->payload['verification_code'], $doc->payload['fiscal_day_no'], $doc->payload['taxpayer_tin']]);

        $this->get(route('accounting.ar.invoices.show', $invoice))->assertOk()->assertSee('Fiscalised with ZIMRA FDMS')->assertSee('ABCD-EF01-2345-6789')->assertDontSee('Send to ZIMRA');
        $this->get(route('accounting.ar.invoices.pdf', $invoice))->assertOk();

        // Never signed twice.
        $submits = count($this->cli->calls);
        $this->post(route('accounting.ar.invoices.zimra', $invoice))->assertSessionHas('success');
        $this->assertCount($submits, $this->cli->calls);
    }

    public function test_a_zimra_failure_never_blocks_posting_and_can_be_retried(): void
    {
        $this->activate();
        $this->cli->failSubmit = 'RCPT010: Wrong currency code';
        $invoice = $this->invoice();

        $this->actingAs($this->admin)->post(route('accounting.ar.invoices.post', $invoice))->assertSessionHas('warning', fn ($m) => str_contains($m, 'RCPT010'));
        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('rejected', EinvoiceDocument::withoutGlobalScopes()->value('status'));
        $this->assertStringContainsString('RCPT010', (string) FiscalDevice::value('last_error'));
        $this->get(route('accounting.ar.invoices.show', $invoice))->assertSee('Not fiscalised yet')->assertSee('Retry ZIMRA');

        $this->cli->failSubmit = null;
        $this->post(route('accounting.ar.invoices.zimra', $invoice))->assertSessionHas('success');
        $this->assertSame('cleared', EinvoiceDocument::withoutGlobalScopes()->value('status'));
        $this->assertNull(FiscalDevice::value('last_error'));
    }

    public function test_companies_without_fiscalisation_are_untouched(): void
    {
        $invoice = $this->invoice();
        $this->actingAs($this->admin)->post(route('accounting.ar.invoices.post', $invoice))->assertSessionHas('success');
        $this->assertSame([], $this->cli->calls);
        $this->get(route('accounting.ar.invoices.show', $invoice))->assertDontSee('Send to ZIMRA');
        $this->post(route('accounting.ar.invoices.zimra', $invoice))->assertSessionHas('error');

        // A draft is never fiscalised, even when set up.
        $this->activate();
        $draft = $this->invoice();
        $this->actingAs($this->admin)->post(route('accounting.ar.invoices.zimra', $draft))->assertSessionHas('error', 'Post the invoice before sending it to ZIMRA.');
    }

    public function test_nightly_close_and_identity_changes(): void
    {
        $this->activate();
        app(\App\Modules\Accounting\Services\ZimraFdmsService::class)->openDay(FiscalDevice::firstOrFail());

        $this->artisan('zimra:close-days')->assertSuccessful();
        $this->assertSame(['day', 'close'], array_slice(collect($this->cli->calls)->last(fn ($c) => $c['args'][0] === 'day')['args'], 0, 2));
        $this->assertSame('FiscalDayClosed', FiscalDevice::value('fiscal_day_status'));

        // A new device ID means registering again.
        $this->actingAs($this->superuser)->put(route('system.fiscalisation.update', $this->company), $this->settings(['device_id' => 99999, 'enabled' => 1]))->assertSessionHas('success', fn ($m) => str_contains($m, 'register it with ZIMRA again'));
        $device = FiscalDevice::firstOrFail();
        $this->assertFalse($device->isActive());
    }
}

/** Stands in for the zimra-fdms CLI: keeps a fiscal day and answers like FDMS. */
class FakeFdmsCli extends FdmsCli
{
    public array $calls = [];
    public ?array $lastReceipt = null;
    public ?string $failSubmit = null;
    private string $day = 'FiscalDayClosed';
    private int $dayNo = 0;
    private int $globalNo = 1000;

    public function run(array $args, string $profileDir, bool $json = false): array|string
    {
        $this->calls[] = ['args' => $args, 'dir' => $profileDir];
        File::ensureDirectoryExists($profileDir);

        return match (implode(' ', array_slice($args, 0, $args[0] === 'day' ? 2 : 1))) {
            'register' => tap('Registered.', fn () => File::put($profileDir.'/device.json', '{}')),
            'config' => ['taxPayerName' => 'Acme Trading (Pvt) Ltd', 'taxPayerTIN' => '2000123456', 'vatNumber' => '220000001', 'deviceBranchName' => 'Harare',
                'certificateValidTill' => '2027-06-10T00:00:00', 'applicableTaxes' => [
                    ['taxID' => 1, 'taxPercent' => 15.5, 'taxName' => 'Standard rated'], ['taxID' => 2, 'taxPercent' => 0, 'taxName' => 'Zero rated'], ['taxID' => 3, 'taxName' => 'Exempt'],
                ]],
            'status' => ['fiscalDayStatus' => $this->day, 'lastFiscalDayNo' => $this->dayNo, 'lastReceiptGlobalNo' => $this->globalNo],
            'day open' => tap('Fiscal day '.($this->dayNo + 1).' opened.', function () use ($profileDir) {
                $this->day = 'FiscalDayOpened';
                $this->dayNo++;
                File::put($profileDir.'/day-state.json', '{}');
            }),
            'day close' => tap('Fiscal day closed.', function () use ($profileDir) {
                $this->day = 'FiscalDayClosed';
                File::delete($profileDir.'/day-state.json');
            }),
            'submit' => $this->submit($args[1]),
        };
    }

    private function submit(string $file): array
    {
        $this->lastReceipt = json_decode(File::get($file), true);
        if ($this->failSubmit) {
            throw new FdmsException($this->failSubmit);
        }
        $this->globalNo++;

        return ['receiptCounter' => 1, 'receiptGlobalNo' => $this->globalNo, 'receiptID' => 'R-'.$this->globalNo, 'total' => 0,
            'qrData' => 'https://fdmstest.zimra.co.zw/00000123451006202600000010'.'abcdef0123456789', 'validationErrors' => []];
    }
}

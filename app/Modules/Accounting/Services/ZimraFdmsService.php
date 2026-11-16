<?php

namespace App\Modules\Accounting\Services;

use App\Models\Platform\FiscalDevice;
use App\Modules\Accounting\Models\EinvoiceDocument;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Services\Fiscal\FdmsCli;
use App\Services\Fiscal\FdmsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ZIMRA FDMS fiscalisation, one fiscal device per company (set up by a superuser under System → Fiscalisation).
 *
 * Each device's identity (certificate + private key) and the fiscal day's receipt counters and hash chain
 * live in its profile directory. Receipts must be signed one after another, so every call that touches
 * the chain holds a per-company lock. A fiscal day is opened automatically before the first receipt and
 * closed each night by `zimra:close-days`.
 */
class ZimraFdmsService
{
    public function __construct(private readonly FdmsCli $cli)
    {
    }

    public function deviceFor(int $companyId): ?FiscalDevice
    {
        return FiscalDevice::where('company_id', $companyId)->first();
    }

    public function isActiveFor(int $companyId): bool
    {
        return (bool) $this->deviceFor($companyId)?->isActive();
    }

    public function profileDir(FiscalDevice $device): string
    {
        return rtrim(config('zimra.profiles_path'), '/').'/'.$device->company_id;
    }

    // ---- device set-up (superuser) -----------------------------------------------------

    /** Registers the device with FDMS (new key pair + certificate), then reads its configuration. */
    public function register(FiscalDevice $device): FiscalDevice
    {
        if (!$device->device_id || !$device->serial_number || !$device->activation_key) {
            throw new FdmsException('Enter the device ID, serial number and activation key from the ZIMRA FDMS portal first.');
        }

        $dir = $this->profileDir($device);
        File::ensureDirectoryExists($dir, 0700);

        $this->locked($device, fn () => $this->cli->run([
            'register',
            '--device-id', (string) $device->device_id,
            '--serial', $device->serial_number,
            '--activation-key', $device->activation_key,
            '--model-name', $device->model_name ?: 'Server',
            '--model-version', $device->model_version ?: 'v1',
            '--env', $device->environment === 'production' ? 'production' : 'test',
            '--force',
        ], $dir));

        // The activation key is single-use; do not keep it once the certificate is issued.
        $device->forceFill(['registered_at' => now(), 'activation_key' => null, 'last_error' => null])->save();

        return $this->refresh($device);
    }

    /** Reads taxpayer details, the tax table, certificate expiry and the fiscal day status from FDMS. */
    public function refresh(FiscalDevice $device): FiscalDevice
    {
        $this->assertRegistered($device);
        $dir = $this->profileDir($device);

        try {
            $config = $this->cli->run(['config'], $dir, json: true);
            $status = $this->cli->run(['status'], $dir, json: true);
        } catch (FdmsException $e) {
            $device->forceFill(['last_error' => $e->getMessage(), 'last_checked_at' => now()])->save();
            throw $e;
        }

        $taxes = collect($config['applicableTaxes'] ?? [])->map(fn ($t) => [
            'id' => (int) ($t['taxID'] ?? 0), 'percent' => $t['taxPercent'] ?? null, 'name' => $t['taxName'] ?? '',
        ])->values()->all();

        $device->forceFill([
            'taxpayer_name' => $config['taxPayerName'] ?? null,
            'taxpayer_tin' => $config['taxPayerTIN'] ?? null,
            'vat_number' => $config['vatNumber'] ?? null,
            'branch_name' => $config['deviceBranchName'] ?? null,
            'certificate_valid_till' => !empty($config['certificateValidTill']) ? \Illuminate\Support\Carbon::parse($config['certificateValidTill']) : null,
            'applicable_taxes' => $taxes,
            // Suggest the tax mapping from FDMS's own table when none is set yet.
            'standard_tax_id' => $device->standard_tax_id ?? collect($taxes)->filter(fn ($t) => (float) $t['percent'] > 0)->sortByDesc('percent')->first()['id'] ?? null,
            'zero_rated_tax_id' => $device->zero_rated_tax_id ?? collect($taxes)->first(fn ($t) => $t['percent'] !== null && (float) $t['percent'] == 0.0)['id'] ?? null,
            'exempt_tax_id' => $device->exempt_tax_id ?? collect($taxes)->first(fn ($t) => $t['percent'] === null)['id'] ?? null,
            'fiscal_day_status' => $status['fiscalDayStatus'] ?? null,
            'fiscal_day_no' => $status['lastFiscalDayNo'] ?? null,
            'last_receipt_global_no' => $status['lastReceiptGlobalNo'] ?? null,
            'last_checked_at' => now(),
            'last_error' => null,
        ])->save();

        return $device;
    }

    public function openDay(FiscalDevice $device): FiscalDevice
    {
        $this->assertRegistered($device);
        $this->locked($device, fn () => $this->openDayUnlocked($device));

        return $this->refresh($device);
    }

    public function closeDay(FiscalDevice $device): FiscalDevice
    {
        $this->assertRegistered($device);
        $this->locked($device, fn () => $this->cli->run(['day', 'close'], $this->profileDir($device)));

        return $this->refresh($device);
    }

    // ---- invoices ---------------------------------------------------------------------------

    /** Fiscalises a posted invoice once; returns the signed (or failed) e-invoice record. */
    public function submitInvoice(SalesInvoice $invoice): EinvoiceDocument
    {
        $invoice->loadMissing(['customer', 'lines']);
        $device = $this->deviceFor((int) $invoice->company_id);
        if (!$device?->isActive()) {
            throw new FdmsException('Fiscalisation is not set up for this company. A platform administrator enables it under System → Fiscalisation.');
        }

        $existing = $this->documentFor($invoice);
        if ($existing && $existing->authority_reference) {
            return $existing; // already fiscalised: never sign the same invoice twice
        }

        $payload = $this->receiptPayload($invoice, $device);

        try {
            $response = $this->locked($device, function () use ($device, $payload, $invoice) {
                $dir = $this->profileDir($device);
                if (!File::exists($dir.'/day-state.json')) {
                    $this->openDayUnlocked($device);
                }

                $file = storage_path('app/private/zimra-receipts/'.$invoice->company_id.'-'.$invoice->id.'-'.Str::uuid().'.json');
                File::ensureDirectoryExists(dirname($file), 0700);
                File::put($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                try {
                    return $this->cli->run(['submit', $file], $dir, json: true);
                } finally {
                    File::delete($file);
                }
            });
        } catch (FdmsException $e) {
            $this->record($invoice, 'rejected', null, ['request' => $payload, 'error' => $e->getMessage()]);
            $device->forceFill(['last_error' => $e->getMessage()])->save();
            throw $e;
        }

        $device->forceFill([
            'fiscal_day_status' => 'FiscalDayOpened',
            'last_receipt_global_no' => $response['receiptGlobalNo'] ?? $device->last_receipt_global_no,
            'last_error' => null,
        ])->save();

        return $this->record($invoice, empty($response['validationErrors']) ? 'cleared' : 'submitted', $response['receiptID'] ?? null, [
            'request' => $payload,
            'response' => $response,
            'device_id' => $device->device_id,
            'device_serial' => $device->serial_number,
            'fiscal_day_no' => $device->fiscal_day_no,
            'taxpayer_name' => $device->taxpayer_name,
            'taxpayer_tin' => $device->taxpayer_tin,
            'vat_number' => $device->vat_number,
            'verification_code' => self::verificationCode($response['qrData'] ?? null),
        ]);
    }

    /** Called after an invoice is posted: fiscalises it when the company has automatic submission on. Never throws. */
    public function autoSubmit(SalesInvoice $invoice): void
    {
        $device = $this->deviceFor((int) $invoice->company_id);
        if (!$device?->isActive() || !$device->auto_submit) {
            return;
        }
        try {
            $this->submitInvoice($invoice);
        } catch (\Throwable $e) {
            Log::warning('ZIMRA fiscalisation of invoice '.$invoice->invoice_number.' failed: '.$e->getMessage());
        }
    }

    public function documentFor(SalesInvoice $invoice): ?EinvoiceDocument
    {
        return EinvoiceDocument::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->where('document_number', $invoice->invoice_number)
            ->latest('id')
            ->first();
    }

    /** The 16-character verification code printed under the QR code (last part of the QR data), as XXXX-XXXX-XXXX-XXXX. */
    public static function verificationCode(?string $qrData): ?string
    {
        if (!$qrData || strlen($qrData) < 16) {
            return null;
        }

        return implode('-', str_split(strtoupper(substr($qrData, -16)), 4));
    }

    /** QR code image for the verification URL as a PNG data URI (works in dompdf PDFs and browsers), or null. */
    public static function qrImage(?string $qrData): ?string
    {
        if (!$qrData || !class_exists(\chillerlan\QRCode\QRCode::class) || !extension_loaded('gd')) {
            return null;
        }

        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
                'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
                'scale' => 6,
                'quietzoneSize' => 2,
                'outputBase64' => true,
            ]);

            return (new \chillerlan\QRCode\QRCode($options))->render($qrData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ZIMRA QR code could not be drawn: '.$e->getMessage());

            return null;
        }
    }

    // ------------------------------------------------------------------------------------------

    private function openDayUnlocked(FiscalDevice $device): void
    {
        $dir = $this->profileDir($device);
        try {
            $output = $this->cli->run(['day', 'open'], $dir);
        } catch (FdmsException $e) {
            // A day left open without local state (e.g. opened on another machine): close it from FDMS's counters, then open a new one.
            if (!str_contains(strtolower($e->getMessage()), 'open')) {
                throw $e;
            }
            $this->cli->run(['day', 'close'], $dir);
            $output = $this->cli->run(['day', 'open'], $dir);
        }
        // "Fiscal day 12 opened."
        $dayNo = preg_match('/Fiscal day (\d+) opened/i', (string) $output, $m) ? (int) $m[1] : $device->fiscal_day_no;
        $device->forceFill(['fiscal_day_status' => 'FiscalDayOpened', 'fiscal_day_no' => $dayNo])->save();
    }

    private function receiptPayload(SalesInvoice $invoice, FiscalDevice $device): array
    {
        $zeroAsExempt = $device->zero_rate_lines_as === 'exempt';
        $lines = $invoice->lines->values()->map(function ($line) use ($device, $zeroAsExempt) {
            $rate = (float) ($line->tax_rate ?? 0);
            [$taxId, $percent] = match (true) {
                $rate > 0 => [$device->standard_tax_id, $rate],
                $zeroAsExempt => [$device->exempt_tax_id, null],
                default => [$device->zero_rated_tax_id, 0],
            };
            if (!$taxId) {
                throw new FdmsException('Set the ZIMRA tax IDs for this company under System → Fiscalisation (missing a tax for '.($rate > 0 ? $rate.'%' : ($zeroAsExempt ? 'exempt' : 'zero-rated')).' lines).');
            }

            return array_filter([
                'name' => Str::limit($line->description, 200, ''),
                // JSON numbers with at most two decimals (the CLI refuses text and finer amounts).
                'price' => round((float) $line->unit_price, 2),
                'quantity' => (float) $line->quantity,
                'taxId' => (int) $taxId,
                'taxPercent' => $percent === null ? null : round((float) $percent, 2),
            ], fn ($v) => $v !== null);
        })->all();

        $customer = $invoice->customer;

        return [
            'receiptType' => 'FiscalInvoice',
            'currency' => $invoice->currency,
            'invoiceNo' => $invoice->invoice_number,
            'linesTaxInclusive' => (bool) $device->lines_tax_inclusive,
            'notes' => 'Sales invoice '.$invoice->invoice_number,
            'buyer' => array_filter([
                'buyerRegisterName' => $customer?->name,
                'buyerTradeName' => $customer?->name,
                'buyerTIN' => $customer?->tin ?: null,
                'vatNumber' => $customer?->vat_number ?: null,
                'buyerContacts' => array_filter(['phoneNo' => $customer?->phone, 'email' => $customer?->email]) ?: null,
            ]),
            'lines' => $lines,
            'payments' => [[
                'moneyType' => $device->payment_type ?: 'Credit',
                'amount' => round((float) $invoice->total, 2),
            ]],
        ];
    }

    private function record(SalesInvoice $invoice, string $status, ?string $reference, array $payload): EinvoiceDocument
    {
        $document = EinvoiceDocument::withoutGlobalScopes()->firstOrNew([
            'company_id' => $invoice->company_id,
            'document_number' => $invoice->invoice_number,
        ]);
        $document->forceFill([
            'company_id' => $invoice->company_id,
            'jurisdiction' => 'ZWE',
            'network' => 'ZIMRA FDMS',
            'taxable_amount' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_total,
            'status' => $status,
            'authority_reference' => $reference,
            'payload' => ['invoice_id' => $invoice->id, 'submitted_at' => now()->toIso8601String()] + $payload,
        ])->save();

        return $document;
    }

    private function assertRegistered(FiscalDevice $device): void
    {
        if (!$device->isRegistered()) {
            throw new FdmsException('Register the device with ZIMRA first.');
        }
    }

    /** One FDMS conversation at a time per company: receipts are numbered and hash-chained in order. */
    private function locked(FiscalDevice $device, callable $callback): mixed
    {
        try {
            return Cache::lock('zimra-fdms:'.$device->company_id, config('zimra.timeout') + 30)->block(config('zimra.timeout'), $callback);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            throw new FdmsException('Another fiscal submission for this company is still running. Try again in a minute.');
        }
    }
}

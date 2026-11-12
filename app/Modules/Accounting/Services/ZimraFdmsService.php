<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\EinvoiceDocument;
use App\Modules\Accounting\Models\SalesInvoice;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class ZimraFdmsService
{
    public function submitInvoice(SalesInvoice $invoice): EinvoiceDocument
    {
        $invoice->loadMissing(['customer', 'lines']);

        $payload = $this->receiptPayload($invoice);
        $workDir = storage_path('app/zimra-fdms');
        File::ensureDirectoryExists($workDir);

        $receiptFile = $workDir.'/receipt-'.$invoice->id.'-'.Str::uuid().'.json';
        File::put($receiptFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $binary = PHP_OS_FAMILY === 'Windows'
            ? base_path('node_modules/.bin/zimra-fdms.cmd')
            : base_path('node_modules/.bin/zimra-fdms');

        $process = new Process([
            $binary,
            'submit',
            $receiptFile,
            '--profile',
            config('zimra.profile'),
            '--json',
        ], base_path(), null, null, 90);

        $process->run();
        File::delete($receiptFile);

        if (!$process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'ZIMRA FDMS submission failed.');
        }

        $response = json_decode($process->getOutput(), true);
        if (!is_array($response)) {
            throw new RuntimeException('ZIMRA FDMS returned an unreadable response.');
        }

        return EinvoiceDocument::updateOrCreate(
            [
                'company_id' => $invoice->company_id,
                'document_number' => $invoice->invoice_number,
            ],
            [
                'jurisdiction' => 'ZWE',
                'network' => 'ZIMRA FDMS',
                'taxable_amount' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_total,
                'status' => empty($response['validationErrors']) ? 'cleared' : 'submitted',
                'authority_reference' => $response['receiptID'] ?? null,
                'payload' => [
                    'invoice_id' => $invoice->id,
                    'request' => $payload,
                    'response' => $response,
                    'submitted_at' => now()->toIso8601String(),
                ],
            ],
        );
    }

    public function documentFor(SalesInvoice $invoice): ?EinvoiceDocument
    {
        return EinvoiceDocument::where('company_id', $invoice->company_id)
            ->where('document_number', $invoice->invoice_number)
            ->latest()
            ->first();
    }

    private function receiptPayload(SalesInvoice $invoice): array
    {
        $lines = $invoice->lines->values()->map(function ($line) {
            $taxPercent = (float) ($line->tax_rate ?? 0);

            return [
                'name' => $line->description,
                'price' => number_format((float) $line->unit_price, 2, '.', ''),
                'quantity' => (float) $line->quantity,
                'taxId' => $taxPercent > 0 ? config('zimra.tax_id') : config('zimra.exempt_tax_id'),
                'taxPercent' => $taxPercent > 0 ? $taxPercent : null,
            ];
        })->all();

        return [
            'receiptType' => 'FiscalInvoice',
            'currency' => $invoice->currency,
            'invoiceNo' => $invoice->invoice_number,
            'linesTaxInclusive' => (bool) config('zimra.lines_tax_inclusive'),
            'notes' => 'Sales invoice '.$invoice->invoice_number,
            'buyer' => [
                'buyerRegisterName' => $invoice->customer?->name,
                'buyerContacts' => [
                    'phoneNo' => $invoice->customer?->phone,
                    'email' => $invoice->customer?->email,
                ],
            ],
            'lines' => $lines,
            'payments' => [
                [
                    'moneyType' => config('zimra.payment_type'),
                    'amount' => number_format((float) $invoice->total, 2, '.', ''),
                ],
            ],
        ];
    }
}

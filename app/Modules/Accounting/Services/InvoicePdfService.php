<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\SalesInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The sales invoice PDF (resources/views/accounting/ar/invoices/document.blade.php), used for staff downloads,
 * the customer portal and emailed invoices so all three are identical.
 */
class InvoicePdfService
{
    public function __construct(private readonly ZimraFdmsService $fdms)
    {
    }

    /** @param  string  $context  staff | portal */
    public function render(SalesInvoice $invoice, string $context = 'staff'): string
    {
        $invoice = SalesInvoice::withoutGlobalScopes()->with(['customer', 'lines'])->findOrFail($invoice->id);

        $pdf = Pdf::loadView('accounting.ar.invoices.document', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'company' => Company::find($invoice->company_id),
            'einvoice' => $this->fdms->documentFor($invoice),
            'context' => $context,
        ])->setPaper('a4', 'portrait');

        // "Page x of y" needs the finished page count, so it is drawn after layout.
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 98, $canvas->get_height() - 25, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 6.5, [0.61, 0.64, 0.69]);

        return $dompdf->output();
    }

    public function filename(SalesInvoice $invoice): string
    {
        return 'invoice-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->invoice_number).'.pdf';
    }
}

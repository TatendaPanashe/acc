<?php

namespace App\Notifications\Customers;

use App\Models\Company;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Notifications\AppMailNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerInvoiceIssued extends AppMailNotification
{
    public function __construct(public SalesInvoice $invoice)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = SalesInvoice::withoutGlobalScopes()->with(['customer', 'lines'])->findOrFail($this->invoice->id);
        $company = Company::find($invoice->company_id);
        $cur = $invoice->currency;
        $pdf = Pdf::loadView('portal.invoice-pdf', ['customer' => $invoice->customer, 'invoice' => $invoice, 'company' => $company])->setPaper('a4', 'portrait')->output();

        $rows = $invoice->lines->map(fn ($l) => [
            $l->description.((float) $l->quantity !== 1.0 ? ' × '.self::number($l->quantity) : ''),
            self::money($cur, $l->line_total),
        ])->all();
        if ((float) $invoice->tax_total > 0) {
            $rows[] = ['Subtotal', self::money($cur, $invoice->subtotal)];
            $rows[] = ['VAT', self::money($cur, $invoice->tax_total)];
        }

        $message = $this->message($company)
            ->subject('Invoice '.$invoice->invoice_number.' from '.$company?->name.': '.self::money($cur, $invoice->total))
            ->preheader(self::money($cur, $invoice->total).($invoice->due_date ? ' due on '.$invoice->due_date->format('d M Y') : '').'. PDF attached.')
            ->eyebrow('Invoice '.$invoice->invoice_number)
            ->greeting('Dear '.$invoice->customer?->name)
            ->line('Thank you for your business. Please find your invoice below; the PDF copy is attached.')
            ->highlight('Amount due', self::money($cur, $invoice->balance > 0 ? $invoice->balance : $invoice->total), $invoice->due_date ? 'Due '.$invoice->due_date->format('l, d M Y') : null)
            ->items($rows, self::money($cur, $invoice->total), 'Invoice total')
            ->summary([
                'Invoice number' => $invoice->invoice_number,
                'Invoice date' => $invoice->invoice_date->format('d M Y'),
                'Due date' => $invoice->due_date?->format('d M Y'),
                'Customer number' => $invoice->customer?->customer_number,
                'VAT number' => $company?->vat_number,
            ], 'Invoice details')
            ->attachData($pdf, 'invoice-'.$invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);

        if ($pay = self::paymentDetails($company, $cur, $invoice->invoice_number)) {
            $message->summary($pay, 'How to pay');
        }

        if ($invoice->customer?->portal_password) {
            $message->action('View in customer portal', route('portal.login'));
        }

        return $message
            ->line('Questions about this invoice? Just reply to this email.')
            ->footnote('Sent to '.$invoice->customer?->email.' because you are a customer of '.$company?->name.'.');
    }
}

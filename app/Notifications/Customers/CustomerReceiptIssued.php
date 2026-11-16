<?php

namespace App\Notifications\Customers;

use App\Models\Company;
use App\Modules\Accounting\Models\CustomerReceipt;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerReceiptIssued extends AppMailNotification
{
    public function __construct(public CustomerReceipt $receipt)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $receipt = CustomerReceipt::withoutGlobalScopes()->with(['customer', 'invoice'])->findOrFail($this->receipt->id);
        $company = Company::find($receipt->company_id);
        $invoice = $receipt->invoice;
        $cur = $receipt->currency;

        return $this->message($company)
            ->subject('Payment received: '.self::money($cur, $receipt->amount).' (receipt '.$receipt->receipt_number.')')
            ->preheader('Thank you. We have received your payment of '.self::money($cur, $receipt->amount).'.')
            ->eyebrow('Receipt '.$receipt->receipt_number)
            ->level('success')
            ->greeting('Dear '.$receipt->customer?->name)
            ->line('Thank you for your payment. Please keep this email as your receipt.')
            ->highlight('Amount received', self::money($cur, $receipt->amount), 'Received '.$receipt->receipt_date->format('d M Y'), 'success')
            ->summary([
                'Receipt number' => $receipt->receipt_number,
                'Date received' => $receipt->receipt_date->format('d M Y'),
                'Payment method' => $receipt->payment_method ? ucwords(str_replace('_', ' ', $receipt->payment_method)) : null,
                'Applied to invoice' => $invoice?->invoice_number,
                'Invoice total' => $invoice ? self::money($invoice->currency, $invoice->total) : null,
                'Balance remaining' => $invoice ? ($invoice->balance > 0 ? self::money($invoice->currency, $invoice->balance) : 'Paid in full') : null,
            ], 'Receipt details')
            ->line('Questions about this receipt? Just reply to this email.')
            ->footnote('Sent to '.$receipt->customer?->email.' because you are a customer of '.$company?->name.'.');
    }
}

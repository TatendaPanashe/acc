<?php

namespace App\Notifications\Customers;

use App\Models\Company;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerInvoiceOverdue extends AppMailNotification
{
    public function __construct(public SalesInvoice $invoice, public int $daysOverdue)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = SalesInvoice::withoutGlobalScopes()->with('customer')->findOrFail($this->invoice->id);
        $company = Company::find($invoice->company_id);
        $cur = $invoice->currency;
        $days = $this->daysOverdue.' '.($this->daysOverdue === 1 ? 'day' : 'days');
        $firm = $this->daysOverdue >= 14;

        $message = $this->message($company)
            ->subject(($firm ? 'Second reminder' : 'Friendly reminder').': invoice '.$invoice->invoice_number.' is '.$days.' overdue')
            ->preheader(self::money($cur, $invoice->balance).' was due on '.$invoice->due_date->format('d M Y').'.')
            ->eyebrow('Payment reminder')
            ->greeting('Dear '.$invoice->customer?->name)
            ->line($firm
                ? 'We have not yet received payment for the invoice below, which is now '.$days.' past its due date. Please arrange payment as soon as possible.'
                : 'This is a friendly reminder that the invoice below is now past its due date. It may simply have been missed.')
            ->highlight('Outstanding', self::money($cur, $invoice->balance), 'Due '.$invoice->due_date->format('d M Y').' · '.$days.' overdue', $firm ? 'danger' : 'warning')
            ->summary([
                'Invoice number' => $invoice->invoice_number,
                'Invoice date' => $invoice->invoice_date->format('d M Y'),
                'Invoice total' => self::money($cur, $invoice->total),
                'Paid so far' => (float) $invoice->amount_paid > 0 ? self::money($cur, $invoice->amount_paid) : null,
                'Balance due' => self::money($cur, $invoice->balance),
            ], 'Invoice details');

        if ($pay = self::paymentDetails($company, $cur, $invoice->invoice_number)) {
            $message->summary($pay, 'How to pay');
        }

        $message->note('If you have already paid, thank you. Please reply with your payment reference so we can match it, and ignore this reminder.', 'Already paid?');

        if ($invoice->customer?->portal_password) {
            $message->action('View in customer portal', route('portal.login'));
        }

        return $message->footnote('Sent to '.$invoice->customer?->email.' because you are a customer of '.$company?->name.'.');
    }
}

<?php

namespace App\Notifications\Account;

use App\Models\Platform\PlatformInvoice;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionPaymentReceived extends AppMailNotification
{
    public function __construct(public PlatformInvoice $invoice)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice;
        $sub = $invoice->subscription;
        $rows = array_map(fn ($line) => [$line['description'], self::money($invoice->currency, $line['amount'])], (array) $invoice->lines);

        return $this->message()
            ->subject('Payment received: '.self::money($invoice->currency, $invoice->amount).' for invoice '.$invoice->number)
            ->preheader('Thank you. Your '.($sub?->plan?->name ?? '').' plan is active.')
            ->eyebrow('Billing · Receipt')
            ->greeting('Thank you'.(isset($notifiable->name) ? ', '.$notifiable->name : '').'!')
            ->line('We have received your payment. This email is your receipt.')
            ->highlight('Amount paid', self::money($invoice->currency, $invoice->amount), 'Paid '.self::date($invoice->paid_at ?? now(), 'd M Y, H:i'), 'success')
            ->items($rows, self::money($invoice->currency, $invoice->amount), 'Total paid')
            ->summary([
                'Invoice' => $invoice->number,
                'Payment method' => $invoice->provider ? ucwords(str_replace('_', ' ', $invoice->provider)) : null,
                'Reference' => $invoice->provider_reference,
                'Plan' => $sub?->plan?->name ? $sub->plan->name.' ('.($invoice->billing_cycle ?? $sub->billing_cycle).')' : null,
                'Service period' => $invoice->period_start ? self::date($invoice->period_start).' – '.self::date($invoice->period_end) : null,
                'Active until' => self::date($sub?->current_period_ends_at),
            ], 'Payment details')
            ->action('View invoice', route('billing.invoices.show', $invoice))
            ->footnote(self::adminFootnote($sub?->company?->name));
    }
}

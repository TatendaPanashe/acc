<?php

namespace App\Notifications\Account;

use App\Models\Platform\Payment;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionPaymentFailed extends AppMailNotification
{
    public function __construct(public Payment $payment)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->payment->invoice;

        return $this->message()
            ->subject('Action needed: payment for invoice '.$invoice->number.' did not go through')
            ->preheader('No money was taken. Please try again or use another payment method.')
            ->eyebrow('Billing · Payment failed')
            ->level('error')
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line('We tried to process your payment but it was not successful. **No money was taken.**')
            ->highlight('Amount due', self::money($this->payment->currency, $this->payment->amount), 'Invoice '.$invoice->number.($invoice->due_at ? ' · due '.self::date($invoice->due_at) : ''), 'danger')
            ->summary([
                'Payment method' => ucwords(str_replace('_', ' ', $this->payment->provider)),
                'Attempted' => self::date($this->payment->updated_at ?? now(), 'd M Y, H:i'),
                'Reason given' => $this->payment->failure_reason,
            ], 'What happened')
            ->note("Check the card or wallet balance and try again\nTry another method: card, EcoCash and other mobile money, or bank transfer", 'What you can do')
            ->action('Try payment again', route('billing.invoices.show', $invoice))
            ->footnote(self::adminFootnote($invoice->company?->name ?? null));
    }
}

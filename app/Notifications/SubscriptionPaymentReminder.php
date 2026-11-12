<?php

namespace App\Notifications;

use App\Models\Platform\PlatformInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentReminder extends Notification
{
    use Queueable;

    public function __construct(public PlatformInvoice $invoice, public string $kind = 'upcoming')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->invoice->currency.' '.number_format((float) $this->invoice->amount, 2);
        $due = $this->invoice->due_at?->format('d M Y');

        return (new MailMessage)
            ->subject($this->kind === 'overdue' ? 'Your acc263 payment is overdue' : 'Your acc263 subscription renews on '.$due)
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line($this->kind === 'overdue'
                ? "We haven't received payment of {$amount} for invoice {$this->invoice->number}. Please pay within the grace period to avoid suspension."
                : "Invoice {$this->invoice->number} for {$amount} is due on {$due}.")
            ->action('Pay invoice', route('billing.invoices.show', $this->invoice))
            ->line('Thank you for using acc263.');
    }
}

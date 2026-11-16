<?php

namespace App\Notifications;

use App\Models\Platform\PlatformInvoice;
use Illuminate\Notifications\Messages\MailMessage;

/** Renewal invoice issued ('upcoming') or unpaid after the period ended ('overdue'). */
class SubscriptionPaymentReminder extends AppMailNotification
{
    public function __construct(public PlatformInvoice $invoice, public string $kind = 'upcoming')
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice;
        $sub = $invoice->subscription;
        $amount = self::money($invoice->currency, $invoice->amount);
        $due = self::date($invoice->due_at);
        $overdue = $this->kind === 'overdue';
        $rows = array_map(fn ($line) => [$line['description'], self::money($invoice->currency, $line['amount'])], (array) $invoice->lines);

        $message = $this->message()
            ->subject($overdue ? 'Overdue: '.$amount.' for invoice '.$invoice->number : 'Your '.config('app.name').' subscription renews on '.$due)
            ->preheader($overdue ? 'Please pay before '.self::date($sub?->grace_ends_at).' to avoid suspension.' : 'Invoice '.$invoice->number.' for '.$amount.' is due on '.$due.'.')
            ->eyebrow($overdue ? 'Billing · Payment overdue' : 'Billing · Renewal')
            ->level($overdue ? 'error' : 'info')
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line($overdue
                ? 'We have not received payment for your subscription renewal yet.'
                : 'Your subscription renews soon. Here is your invoice for the next period.')
            ->highlight($overdue ? 'Amount overdue' : 'Amount due', $amount, 'Invoice '.$invoice->number.' · due '.$due, $overdue ? 'danger' : 'primary')
            ->items($rows, $amount, 'Total due')
            ->summary([
                'Plan' => $sub?->plan?->name ? $sub->plan->name.' ('.($invoice->billing_cycle ?? $sub->billing_cycle).')' : null,
                'Service period' => $invoice->period_start ? self::date($invoice->period_start).' – '.self::date($invoice->period_end) : null,
                'Due date' => $due,
                'Grace period ends' => $overdue ? self::date($sub?->grace_ends_at) : null,
            ], 'Invoice details');

        if ($overdue) {
            $message->note('If payment is not received by '.(self::date($sub?->grace_ends_at) ?? 'the end of the grace period').', the workspace will be suspended until the invoice is paid. Your data is never deleted.', 'Avoid interruption', 'warning');
        }

        return $message
            ->action('Pay invoice', route('billing.invoices.show', $invoice))
            ->line('You can pay by card, EcoCash and other mobile money, or bank transfer. Already paid? Thank you, and please ignore this email.')
            ->footnote(self::adminFootnote($invoice->company?->name));
    }
}

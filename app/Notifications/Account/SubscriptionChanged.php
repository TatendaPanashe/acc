<?php

namespace App\Notifications\Account;

use App\Models\Platform\SubscriptionEvent;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** Plan changes and lifecycle events on the workspace subscription (see SubscriptionService). */
class SubscriptionChanged extends AppMailNotification
{
    /** Event types that send this email; others are covered by their own emails or need none. */
    public const TYPES = ['plan_changed', 'promo_granted', 'promo_ended', 'trial_extended', 'trial_expired', 'suspended', 'reactivated', 'cancelled'];

    public function __construct(public SubscriptionEvent $event)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sub = $this->event->subscription;
        $d = (array) $this->event->details;
        $plan = $sub?->plan?->name ?? 'your plan';
        $app = config('app.name');
        $price = $sub && (float) $sub->price > 0 ? self::money($sub->currency, $sub->price).' / '.($sub->billing_cycle === 'annual' ? 'year' : 'month') : null;

        // [subject, eyebrow, intro, highlight [label, value, note, tone], note [title, text, tone]|null, action, level]
        [$subject, $eyebrow, $intro, $highlight, $note, $action, $level] = match ($this->event->type) {
            'plan_changed' => ['Your plan has changed to '.$plan, 'Plan changed',
                'Your workspace has moved to a new plan. The change applies straight away.',
                ['New plan', $plan, $price, 'primary'], null, 'View plan & billing', 'info'],
            'promo_granted' => ['Good news: '.$plan.' free for '.($d['months'] ?? '').' months', 'Promotion',
                'You have been given free access to the **'.$plan.'** plan.',
                ['Free until', self::date($d['ends'] ?? null) ?? '', $plan.' plan, '.($d['months'] ?? '').' months', 'success'], null, 'View plan & billing', 'success'],
            'promo_ended' => ['Your free '.$plan.' period has ended', 'Promotion ended',
                'Your promotional period has ended. Choose a plan to keep using '.$app.'.',
                ['Status', 'Plan needed', 'Your data is safe', 'warning'],
                ['Your data is kept', 'Nothing is deleted. Choose a plan to unlock the workspace for everyone.', 'info'], 'Choose a plan', 'info'],
            'trial_extended' => ['Your trial has been extended to '.self::date($d['to'] ?? null), 'Trial extended',
                'More time to try everything: your free trial has been extended.',
                ['Trial now ends', self::date($d['to'] ?? null) ?? '', ($d['days'] ?? '') ? '+'.$d['days'].' days' : null, 'success'], null, 'Choose a plan', 'success'],
            'trial_expired' => ['Your '.$app.' trial has ended', 'Trial ended',
                'Your free trial has ended, so the workspace is now locked.',
                ['Status', 'Locked', 'Trial ended '.self::date($d['ended'] ?? now()), 'warning'],
                ['Your data is kept', "Nothing is deleted. Choose a plan and everyone can sign in again straight away.", 'info'], 'Choose a plan', 'info'],
            'suspended' => ['Your '.$app.' workspace has been suspended', 'Account suspended',
                'Your workspace has been suspended and your team cannot use it right now.',
                ['Status', 'Suspended', self::date(now()), 'danger'],
                ['Reason', ($d['reason'] ?? 'Not given')."\nCompany Admins can still sign in to settle any outstanding invoice, which reopens the workspace.", 'danger'], 'Go to billing', 'error'],
            'reactivated' => ['Your '.$app.' workspace is active again', 'Reactivated',
                'Welcome back! Your workspace has been reactivated and everyone can sign in again.',
                ['Status', 'Active', $plan, 'success'], null, 'Open '.$app, 'success'],
            'cancelled' => ['Your '.$app.' subscription has been cancelled', 'Cancelled',
                'Your subscription has been cancelled.',
                ['Status', 'Cancelled', self::date(now()), 'warning'],
                ['Reason', ($d['reason'] ?? 'Not given')."\nYour data is kept. Choose a plan any time to come back.", 'info'], 'View billing', 'info'],
            default => ['Your '.$app.' subscription was updated', 'Subscription', 'Your subscription was updated.', ['Plan', $plan, null, 'primary'], null, 'View billing', 'info'],
        };

        $message = $this->message()
            ->subject($subject)
            ->preheader(strip_tags(str_replace('**', '', $intro)))
            ->eyebrow('Billing · '.$eyebrow)
            ->level($level)
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line($intro)
            ->highlight(...$highlight)
            ->summary([
                'Workspace' => $sub?->company?->name,
                'Plan' => $sub?->plan?->name,
                'Billing' => $price,
                'Paid until' => in_array($this->event->type, ['plan_changed', 'reactivated'], true) ? self::date($sub?->current_period_ends_at) : null,
                'Note' => $d['note'] ?? null,
            ], 'Subscription');

        if ($note) {
            $message->note($note[1], $note[0], $note[2]);
        }

        return $message
            ->action($action, $this->event->type === 'reactivated' ? route('dashboard') : route('billing.index'))
            ->footnote(self::adminFootnote($sub?->company?->name));
    }
}

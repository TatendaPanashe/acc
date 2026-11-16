<?php

namespace App\Notifications\Account;

use App\Models\Platform\Subscription;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class TrialEndingSoon extends AppMailNotification
{
    public function __construct(public Subscription $subscription, public int $daysLeft)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sub = $this->subscription;
        $plan = $sub->plan;
        $ends = self::date($sub->trial_ends_at);
        $when = $this->daysLeft <= 1 ? 'tomorrow' : 'in '.$this->daysLeft.' days';

        return $this->message()
            ->subject('Your '.config('app.name').' trial ends '.$when)
            ->preheader('Choose a plan before '.$ends.' to keep full access.')
            ->eyebrow('Billing · Trial')
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line('Your free trial is nearly over. Choose a plan to keep working without interruption.')
            ->highlight('Trial ends', $ends, $this->daysLeft <= 1 ? 'Less than a day left' : $this->daysLeft.' days left', $this->daysLeft <= 1 ? 'danger' : 'warning')
            ->summary([
                'Current plan' => $plan?->name,
                'Monthly price' => $plan ? self::money($plan->currency, $plan->monthly_price).' / month' : null,
                'Annual price' => $plan && (float) $plan->annual_price > 0 ? self::money($plan->currency, $plan->annual_price).' / year' : null,
                'Workspace' => $sub->company?->name,
            ], 'Your trial')
            ->note('If the trial ends without a plan, the workspace is locked but nothing is deleted. Choose a plan any time to pick up where you left off.', 'What happens to my data?')
            ->action('Choose a plan', route('billing.index'))
            ->line('You can pay by card, EcoCash and other mobile money, or bank transfer.')
            ->footnote(self::adminFootnote($sub->company?->name));
    }
}

<?php

namespace App\Notifications\Account;

use App\Models\User;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** To the platform operator when a new business signs up. */
class NewSignupAlert extends AppMailNotification
{
    public function __construct(public User $user, public ?string $planName = null)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sub = $this->user->company?->subscription;

        return $this->message()
            ->subject('New sign-up: '.$this->user->name.($this->planName ? ' ('.$this->planName.' trial)' : ''))
            ->preheader($this->user->name.' just started a trial.')
            ->eyebrow('Platform · New sign-up')
            ->greeting('A new business has signed up')
            ->highlight('Trial plan', $this->planName ?? 'Default plan', $sub?->trial_ends_at ? 'Trial ends '.self::date($sub->trial_ends_at) : null, 'success')
            ->summary([
                'Name' => $this->user->name,
                'Email' => $this->user->email,
                'Username' => $this->user->username,
                'Workspace ID' => '#'.$this->user->company_id,
                'Signed up' => self::date(now(), 'd M Y, H:i'),
            ], 'Sign-up details')
            ->line('A quick welcome call in the first few days helps trials convert.')
            ->action('View tenant', route('system.tenants.show', $this->user->company_id))
            ->footnote('Sent to the platform operator inbox (NOTIFICATIONS_PLATFORM_EMAIL).');
    }
}

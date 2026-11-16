<?php

namespace App\Notifications\Account;

use App\Models\User;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeToWorkspace extends AppMailNotification
{
    public function __construct(public User $user, public ?string $planName = null, public ?string $trialEndsOn = null)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return $this->message()
            ->subject('Welcome to '.$app.', your workspace is ready')
            ->preheader('Your free trial has started. Here is how to get set up in a few minutes.')
            ->eyebrow('Welcome')
            ->greeting('Welcome aboard, '.$this->user->name.'!')
            ->line('Thanks for choosing '.$app.'. Your workspace is ready and your free trial has started, with no card needed.')
            ->highlight('Free trial', $this->planName ? $this->planName.' plan' : 'Started', $this->trialEndsOn ? 'Full access until '.$this->trialEndsOn : null, 'success')
            ->summary([
                'Username' => $this->user->username,
                'Email' => $this->user->email,
                'Role' => 'Company Admin',
                'Trial ends' => $this->trialEndsOn,
            ], 'Your account')
            ->line('**Get set up in four steps:**')
            ->line("1. **Company profile**: your name, logo, TIN, VAT and NSSA numbers.  \n2. **Currencies**: your base currency (USD, ZiG, ZAR…) and the others you trade in.  \n3. **Tax codes**: standard, zero-rated and exempt VAT.  \n4. **Chart of accounts**: start from our ready-made standard set.")
            ->action('Finish company setup', route('company.setup.edit'))
            ->line('After that, invite your team under **Company → Team** and choose a plan from **Billing & Plan** whenever you are ready.')
            ->line('Need a hand? The Help Centre has step-by-step guides, or just reply to this email.')
            ->footnote('You are receiving this because you created an account on '.$app.' with this email address.');
    }
}

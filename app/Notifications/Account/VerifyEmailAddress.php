<?php

namespace App\Notifications\Account;

use App\Notifications\AppMailMessage;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * "Confirm your email" with the signed verification link, in the acc263 email layout.
 * Sent straight away (not queued) so the person can carry on while they wait for it.
 */
class VerifyEmailAddress extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        $app = config('app.name');
        $minutes = (int) Config::get('auth.verification.expire', 60);

        return (new AppMailMessage)->brand(null)
            ->subject('Confirm your email to open '.$app)
            ->preheader('One click and your workspace is ready.')
            ->eyebrow('Confirm your email')
            ->greeting('Almost there!')
            ->line('Please confirm this is your email address so we can open your '.$app.' workspace.')
            ->action('Confirm my email', $url)
            ->line('The link works for '.$minutes.' minutes. If it has expired, sign in and we will send you a new one.')
            ->footnote('If you did not create an account on '.$app.', you can ignore this email and nothing will happen.');
    }
}

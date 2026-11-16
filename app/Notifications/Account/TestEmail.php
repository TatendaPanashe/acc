<?php

namespace App\Notifications\Account;

use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class TestEmail extends AppMailNotification
{
    public function __construct(public ?string $companyName = null)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->message()
            ->subject('Test email from '.config('app.name'))
            ->preheader('Your email settings are working.')
            ->eyebrow('Settings · Test')
            ->greeting('Email is working')
            ->line('This is a test email from '.config('app.name').($this->companyName ? ' for **'.$this->companyName.'**' : '').'. If you are reading it, notifications will reach this address.')
            ->highlight('Status', 'Delivered', 'Sent '.self::date(now(), 'd M Y, H:i'), 'success')
            ->summary([
                'Sender' => config('mail.from.address'),
                'Mail server' => config('mail.mailers.'.config('mail.default').'.host'),
                'Workspace' => $this->companyName,
            ], 'Settings used')
            ->line('If this landed in spam, add SPF and DKIM records for your sending domain with your mail provider.');
    }
}

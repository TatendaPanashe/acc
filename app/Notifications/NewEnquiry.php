<?php

namespace App\Notifications;

use App\Models\Platform\Enquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewEnquiry extends Notification
{
    public function __construct(public Enquiry $enquiry)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $e = $this->enquiry;

        return (new MailMessage)
            ->subject('Website enquiry: '.$e->topicLabel().' from '.$e->name)
            ->replyTo($e->email, $e->name)
            ->line('**From:** '.$e->name.' <'.$e->email.'>'.($e->company ? ', '.$e->company : ''))
            ->line($e->phone ? '**Phone:** '.$e->phone : '')
            ->line('**Topic:** '.$e->topicLabel())
            ->line($e->message)
            ->action('View enquiries', route('system.enquiries.index'));
    }
}

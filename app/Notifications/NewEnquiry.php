<?php

namespace App\Notifications;

use App\Models\Platform\Enquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Website contact form → platform inbox. Sent immediately (not queued) so enquiries never wait on a worker. */
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

        return (new AppMailMessage)->brand(null)
            ->subject('Website enquiry: '.$e->topicLabel().' from '.$e->name)
            ->replyTo($e->email, $e->name)
            ->preheader(\Illuminate\Support\Str::limit((string) $e->message, 90))
            ->eyebrow('Website · New enquiry')
            ->greeting('New enquiry from '.$e->name)
            ->highlight('Topic', $e->topicLabel(), 'Received '.now()->format('d M Y, H:i'))
            ->summary([
                'Name' => $e->name,
                'Email' => $e->email,
                'Phone' => $e->phone,
                'Company' => $e->company,
            ], 'Contact details')
            ->note((string) $e->message, 'Message')
            ->action('View enquiries', route('system.enquiries.index'))
            ->footnote('Reply to this email to answer '.$e->name.' directly.');
    }
}

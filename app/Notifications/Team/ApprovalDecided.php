<?php

namespace App\Notifications\Team;

use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** To the preparer when their document is approved or returned. */
class ApprovalDecided extends AppMailNotification
{
    public function __construct(
        public string $document,     // e.g. "Supplier invoice SI-0004"
        public string $decision,     // approved | rejected
        public string $decidedBy,
        public ?string $note = null,
        public ?string $url = null,
        public ?string $amount = null,
        public ?int $companyId = null,
    ) {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->decision === 'approved';
        $company = $this->companyId ? \App\Models\Company::find($this->companyId) : null;

        $message = $this->message($company)
            ->subject(($approved ? 'Approved: ' : 'Returned: ').$this->document)
            ->preheader($this->document.' was '.($approved ? 'approved' : 'returned for changes').' by '.$this->decidedBy.'.')
            ->eyebrow('Approvals · '.($approved ? 'Approved' : 'Returned'))
            ->level($approved ? 'success' : 'info')
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line($approved
                ? 'Good news: a document you prepared has been approved and finalised.'
                : 'A document you prepared has been returned to you for changes.')
            ->highlight($approved ? 'Approved' : 'Returned for changes', $this->document, $this->amount, $approved ? 'success' : 'warning')
            ->summary([
                'Document' => $this->document,
                'Amount' => $this->amount,
                'Decided by' => $this->decidedBy,
                'Decided on' => self::date(now(), 'd M Y, H:i'),
            ], 'Decision');

        if ($this->note) {
            $message->note($this->note, $approved ? 'Comment from '.$this->decidedBy : 'What needs to change', $approved ? 'info' : 'warning');
        }
        if (!$approved) {
            $message->line('Make the changes and save the document: it goes straight back to the approval queue.');
        }

        return ($this->url ? $message->action('Open document', $this->url) : $message)->footnote(self::optionalFootnote());
    }
}

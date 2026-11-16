<?php

namespace App\Notifications\Team;

use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class PendingApprovalsDigest extends AppMailNotification
{
    /** @param array<string, int> $counts label => documents waiting */
    public function __construct(public string $companyName, public array $counts, public int $oldestDays, public ?int $companyId = null)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = array_sum($this->counts);
        $rows = [];
        foreach ($this->counts as $label => $count) {
            $rows[] = [$label, (string) $count];
        }
        $company = $this->companyId ? \App\Models\Company::find($this->companyId) : null;

        $message = $this->message($company)
            ->subject($total.' '.($total === 1 ? 'document is' : 'documents are').' waiting for your approval')
            ->preheader($total.' waiting at '.$this->companyName.($this->oldestDays > 0 ? ', the oldest for '.$this->oldestDays.' days' : '').'.')
            ->eyebrow('Approvals · Daily digest')
            ->greeting('Good morning '.($notifiable->name ?? ''))
            ->line('Here is what is waiting for approval at **'.$this->companyName.'** today.')
            ->highlight('Waiting for approval', (string) $total, $this->oldestDays > 0 ? 'Oldest waiting '.$this->oldestDays.' '.($this->oldestDays === 1 ? 'day' : 'days') : 'All submitted today', $this->oldestDays >= 3 ? 'warning' : 'primary')
            ->items($rows, (string) $total, 'Total', ['Document type', 'Waiting']);

        if ($this->oldestDays >= 3) {
            $message->note('Some documents have been waiting '.$this->oldestDays.' days. Unposted documents keep the ledger, payables and stock figures out of date.', 'Worth a look today', 'warning');
        }

        return $message
            ->action('Review approvals', route('accounting.approvals-audit.index'))
            ->footnote('Sent each morning to Company Admins and Finance Managers when documents are waiting. '.self::optionalFootnote());
    }
}

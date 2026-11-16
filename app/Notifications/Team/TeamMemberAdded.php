<?php

namespace App\Notifications\Team;

use App\Models\User;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** The password is set by the admin and never emailed. */
class TeamMemberAdded extends AppMailNotification
{
    private const ROLE_SUMMARY = [
        'admin' => 'Full access: company settings, team, billing, approvals and all accounting.',
        'finance_manager' => 'Approve and post documents, run reports and manage day-to-day finance.',
        'accountant' => 'Capture invoices, receipts and journals; post sales; send other documents for approval.',
        'auditor' => 'Read-only access to the books, reports and the audit trail.',
    ];

    public function __construct(public User $member, public string $addedBy)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = $this->member->company;

        return $this->message($company)
            ->subject($this->addedBy.' added you to '.$company?->name.' on '.config('app.name'))
            ->preheader('You now have '.$this->member->roleLabel().' access. Here is how to sign in.')
            ->eyebrow('Team · Invitation')
            ->greeting('Hello '.$this->member->name)
            ->line('**'.$this->addedBy.'** has added you to **'.$company?->name.'** on '.config('app.name').', the accounting, payroll and invoicing system your team uses.')
            ->highlight('Your role', $this->member->roleLabel(), self::ROLE_SUMMARY[$this->member->role] ?? null)
            ->summary([
                'Username' => $this->member->username,
                'Email' => $this->member->email,
                'Company' => $company?->name,
            ], 'Your sign-in')
            ->note('For security we never email passwords. Ask '.$this->addedBy.' for your password, then change it after you first sign in.', 'Your password', 'warning')
            ->action('Sign in', route('login'))
            ->footnote('You are receiving this because '.$this->addedBy.' added this email address to '.$company?->name.'.');
    }
}

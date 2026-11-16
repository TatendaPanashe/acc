<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * One-time portal password for an employee (staff portal) or customer (client portal).
 * The password must be changed at first sign-in; the queued job is encrypted.
 */
class PortalAccessIssued extends AppMailNotification implements ShouldBeEncrypted
{
    public function __construct(public string $portal, public string $name, public string $email, public string $password, public int $companyId)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = Company::find($this->companyId);
        $staff = $this->portal === 'staff';
        $what = $staff ? 'employee self-service portal' : 'customer portal';

        return $this->message($company)
            ->subject('Your '.$what.' sign-in for '.$company?->name)
            ->preheader('Your one-time password is inside. You will choose your own password when you sign in.')
            ->eyebrow($staff ? 'Employee portal' : 'Customer portal')
            ->greeting('Hello '.$this->name)
            ->line($company?->name.' has given you access to the '.$what.'.')
            ->line($staff
                ? "In the portal you can:\n\n- view and download your payslips\n- apply for leave and see your balances\n- claim overtime and follow your requests"
                : "In the portal you can:\n\n- view and download your invoices\n- see what is outstanding\n- tell us about payments you have made")
            ->summary([
                'Sign-in email' => $this->email,
                'One-time password' => new \Illuminate\Support\HtmlString('<code>'.e($this->password).'</code>'),
            ], 'Your sign-in details')
            ->note('This password works once. You will be asked to choose your own password the first time you sign in. Do not share it with anyone.', 'Keep this safe', 'warning')
            ->action('Sign in', route($staff ? 'staff.login' : 'portal.login'))
            ->footnote('Not expecting this? You can ignore this email; nobody can sign in without the password above.');
    }
}

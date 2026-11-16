<?php

namespace App\Notifications\Payroll;

use App\Models\Company;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Services\SelfServiceRequestService;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** To the employee when their leave or overtime request is approved or declined. */
class SelfServiceRequestDecided extends AppMailNotification
{
    public function __construct(public LeaveRequest|OvertimeRequest $request)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;
        $employee = $r->employee;
        $approved = $r->status === 'approved';
        $leave = $r instanceof LeaveRequest;
        $kind = $leave ? 'leave' : 'overtime';
        $what = $leave
            ? ($r->type?->name ?? 'Leave').', '.$r->starts_on->format('d M').' – '.$r->ends_on->format('d M Y')
            : self::number($r->hours).' hours on '.$r->work_date->format('d M Y');

        $message = $this->message(Company::find($r->company_id))
            ->subject('Your '.$kind.' request was '.($approved ? 'approved' : 'declined').': '.$what)
            ->preheader(ucfirst($kind).' '.($approved ? 'approved' : 'declined').($r->decider ? ' by '.$r->decider->name : '').'.')
            ->eyebrow('Payroll · '.ucfirst($kind).' request')
            ->level($approved ? 'success' : 'info')
            ->greeting('Hello '.$employee?->first_name)
            ->line('There is a decision on your '.$kind.' request.')
            ->highlight($approved ? 'Approved' : 'Declined', $what, $r->decider ? 'Decided by '.$r->decider->name.' on '.self::date($r->decided_at) : null, $approved ? 'success' : 'danger');

        if ($leave) {
            $balance = $employee ? (app(SelfServiceRequestService::class)->balances($employee, $r->starts_on->year)[$r->leave_type_id] ?? null) : null;
            $message->summary([
                'Leave type' => $r->type?->name,
                'From' => $r->starts_on->format('l, d M Y'),
                'To' => $r->ends_on->format('l, d M Y'),
                'Working days' => self::number($r->days),
                ($r->type?->name ?? 'Leave').' left in '.$r->starts_on->year => $balance && $balance['remaining'] !== null ? self::number($balance['remaining']).' of '.self::number($balance['entitlement']).' days' : null,
            ], 'Your request');
        } else {
            $message->summary([
                'Date worked' => $r->work_date->format('l, d M Y'),
                'Hours' => self::number($r->hours),
                'Paid' => $approved ? 'With the payroll for '.$r->work_date->format('F Y') : null,
            ], 'Your request');
        }

        if ($r->decision_note) {
            $message->note($r->decision_note, 'Comment', $approved ? 'info' : 'warning');
        }

        if ($employee?->portal_password) {
            $message->action('Open employee portal', route('staff.dashboard'));
        }

        return $message->footnote('Questions about this decision? Reply to this email to reach '.(Company::find($r->company_id)?->name ?? 'your employer').'.');
    }
}

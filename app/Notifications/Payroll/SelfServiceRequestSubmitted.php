<?php

namespace App\Notifications\Payroll;

use App\Models\Company;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Services\SelfServiceRequestService;
use App\Notifications\AppMailNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** To approvers when an employee submits leave or overtime from the portal. */
class SelfServiceRequestSubmitted extends AppMailNotification
{
    public function __construct(public LeaveRequest|OvertimeRequest $request)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;
        $employee = $r->employee;
        $leave = $r instanceof LeaveRequest;
        $kind = $leave ? 'Leave' : 'Overtime';

        $message = $this->message(Company::find($r->company_id))
            ->subject($kind.' request from '.$employee?->name.($leave ? ': '.$r->starts_on->format('d M').' – '.$r->ends_on->format('d M') : ': '.self::number($r->hours).' hours'))
            ->preheader($employee?->name.' is waiting for a decision.')
            ->eyebrow('Payroll · '.$kind.' request')
            ->greeting('Hello '.($notifiable->name ?? ''))
            ->line('**'.$employee?->name.'** has submitted a '.strtolower($kind).' request that needs your decision.');

        if ($leave) {
            $balance = $employee ? (app(SelfServiceRequestService::class)->balances($employee, $r->starts_on->year)[$r->leave_type_id] ?? null) : null;
            $message->highlight($r->type?->name ?? 'Leave', self::number($r->days).' working '.((float) $r->days === 1.0 ? 'day' : 'days'), $r->starts_on->format('D d M').' – '.$r->ends_on->format('D d M Y'))
                ->summary([
                    'Employee' => $employee?->name.' ('.$employee?->employee_number.')',
                    'Job title' => $employee?->job_title,
                    'Department' => $employee?->department,
                    'Leave type' => $r->type?->name,
                    'From' => $r->starts_on->format('l, d M Y'),
                    'To' => $r->ends_on->format('l, d M Y'),
                    'Balance after this request' => $balance && $balance['remaining'] !== null ? self::number($balance['remaining']).' of '.self::number($balance['entitlement']).' days' : null,
                ], 'Request details');
        } else {
            $message->highlight('Overtime claimed', self::number($r->hours).' hours', 'Worked on '.$r->work_date->format('D d M Y'))
                ->summary([
                    'Employee' => $employee?->name.' ('.$employee?->employee_number.')',
                    'Job title' => $employee?->job_title,
                    'Department' => $employee?->department,
                    'Date worked' => $r->work_date->format('l, d M Y'),
                    'Hours' => self::number($r->hours),
                ], 'Request details');
        }

        if ($r->reason) {
            $message->note($r->reason, 'Reason given');
        }

        return $message
            ->action('Review request', route('accounting.payroll.requests.index'))
            ->footnote('Sent to Company Admins and Finance Managers. '.self::optionalFootnote());
    }
}

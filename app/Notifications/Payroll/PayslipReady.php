<?php

namespace App\Notifications\Payroll;

use App\Models\Company;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayrollRunService;
use App\Notifications\AppMailNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Employees with portal access get a link; others get the payslip PDF attached.
 * Encrypted on the queue because the PDF holds pay details.
 */
class PayslipReady extends AppMailNotification implements ShouldBeEncrypted
{
    public function __construct(public PayrollRun $run, public Employee $employee)
    {
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = Company::find($this->employee->company_id);
        $slips = Payslip::withoutGlobalScopes()->where('payroll_run_id', $this->run->id)->where('employee_id', $this->employee->id)->get();
        $period = $this->run->period_start->format('d M').' – '.$this->run->period_end->format('d M Y');
        $hasPortal = (bool) $this->employee->portal_password;
        $netText = $slips->map(fn ($s) => self::money($s->currency, $s->net))->implode(' + ');

        $message = $this->message($company)
            ->subject('Your payslip for '.$this->run->period_end->format('F Y').' is ready')
            ->preheader('Net pay '.$netText.', paid on '.$this->run->pay_date->format('d M Y').'.')
            ->eyebrow('Payroll · Payslip')
            ->greeting('Hello '.$this->employee->first_name)
            ->line('Your payslip for **'.$this->run->period_end->format('F Y').'** is ready.')
            ->highlight('Net pay', $netText, 'Pay date '.$this->run->pay_date->format('d M Y'), 'success');

        foreach ($slips as $slip) {
            $tax = (float) $slip->paye + (float) $slip->aids_levy;
            $message->summary([
                'Gross pay' => self::money($slip->currency, $slip->gross),
                'PAYE and AIDS levy' => $tax > 0 ? '− '.self::money($slip->currency, $tax) : null,
                'NSSA' => (float) $slip->nssa_employee > 0 ? '− '.self::money($slip->currency, $slip->nssa_employee) : null,
                'Other deductions' => (float) $slip->other_deductions > 0 ? '− '.self::money($slip->currency, $slip->other_deductions) : null,
                'Net pay' => self::money($slip->currency, $slip->net),
            ], 'Summary'.($slips->count() > 1 ? ' ('.$slip->currency.')' : ''));
        }

        $message->summary([
            'Employee' => $this->employee->name.' ('.$this->employee->employee_number.')',
            'Pay period' => $period,
            'Payroll' => $this->run->run_number,
            'Paid by' => $this->employee->payment_method ? ucwords(str_replace('_', ' ', $this->employee->payment_method)) : null,
        ], 'Details');

        if ($hasPortal) {
            return $message
                ->line('The full payslip, with every earning and deduction and your year-to-date totals, is in the employee portal.')
                ->action('View payslip', route('staff.payslips'))
                ->footnote('Your pay details are confidential. If anything looks wrong, please contact '.($company?->name ?? 'payroll').' payroll.');
        }

        $pdf = Pdf::loadView('payroll.payslip-pdf', [
            'run' => $this->run, 'employee' => $this->employee, 'slips' => $slips, 'company' => $company,
            'ytd' => app(PayrollRunService::class)->yearToDate($this->employee, $this->run),
        ])->setPaper('a4', 'portrait')->output();

        return $message
            ->note('Your full payslip, with every earning and deduction and your year-to-date totals, is attached as a PDF.', 'Payslip attached')
            ->attachData($pdf, 'payslip-'.$this->run->run_number.'.pdf', ['mime' => 'application/pdf'])
            ->footnote('Your pay details are confidential. If anything looks wrong, please contact '.($company?->name ?? 'payroll').' payroll.');
    }
}

<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\RunInput;
use App\Modules\Payroll\Models\StatutorySetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll run lifecycle: draft → calculate (any number of times) → approve (posts the payroll journal per
 * currency) → paid (posts the net pay out of cash, bank or mobile money).
 *
 * Payroll journal (per currency, dated at the end of the period):
 *   Dr Salaries & wages (or each earning's own account)   gross
 *   Dr Employer payroll contributions                     employer NSSA + employer levies
 *   Cr PAYE payable                                        PAYE + AIDS levy
 *   Cr NSSA payable                                        employee + employer NSSA
 *   Cr Deductions payable (or each deduction's account)   other deductions and levies
 *   Cr Net wages payable                                   net pay
 */
class PayrollRunService
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly ExchangeRateService $rates,
        private readonly AccountMappingService $mappings,
        private readonly JournalPostingService $journals,
    ) {
    }

    public function create(Carbon $start, Carbon $end, Carbon $payDate, ?string $notes = null): PayrollRun
    {
        $base = 'PR-'.$end->format('Ym');
        $number = $base;
        for ($i = 2; PayrollRun::where('run_number', $number)->exists(); $i++) {
            $number = $base.'-'.$i;
        }

        return PayrollRun::create([
            'run_number' => $number, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
            'pay_date' => $payDate->toDateString(), 'status' => 'draft', 'created_by' => auth()->id(), 'notes' => $notes,
        ]);
    }

    /** Employees paid in the run's period. */
    public function employeesFor(PayrollRun $run): Collection
    {
        return Employee::where(fn ($q) => $q->where('status', 'active')
                ->orWhere(fn ($t) => $t->where('status', 'terminated')->whereDate('end_date', '>=', $run->period_start->toDateString())))
            ->orderBy('employee_number')->get()
            ->filter(fn (Employee $e) => $e->isPayableIn($run->period_start, $run->period_end))->values();
    }

    /** Missing statutory setup for the run: currency => message. */
    public function missingSettings(PayrollRun $run, ?Collection $employees = null): array
    {
        $employees ??= $this->employeesFor($run);
        // Mixed USD/ZWG salaries are taxed on the USD table (ZIMRA), so only each employee's tax currency needs settings.
        $currencies = $employees->map(fn (Employee $e) => PayrollCalculator::taxCurrency($e))->unique();
        $settings = StatutorySetting::withCount('bands')->where('tax_year', $run->taxYear())->get()->keyBy('currency');
        $missing = [];
        foreach ($currencies as $currency) {
            if (!isset($settings[$currency])) {
                $missing[$currency] = 'No '.$currency.' statutory settings for tax year '.$run->taxYear().'.';
            } elseif ($settings[$currency]->bands_count === 0) {
                $missing[$currency] = 'The '.$currency.' PAYE table for '.$run->taxYear().' has no bands.';
            }
        }

        return $missing;
    }

    public function calculate(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            $run = PayrollRun::lockForUpdate()->findOrFail($run->id);
            $this->assertStatus($run, ['draft']);
            $employees = $this->employeesFor($run);
            if ($employees->isEmpty()) {
                throw ValidationException::withMessages(['run' => 'There are no active employees in this period.']);
            }
            if ($missing = $this->missingSettings($run, $employees)) {
                throw ValidationException::withMessages(['settings' => implode(' ', $missing).' Add them under Payroll → Statutory settings.']);
            }

            $this->syncApprovedOvertime($run, $employees);
            $settings = StatutorySetting::with('bands')->where('tax_year', $run->taxYear())->get()->keyBy('currency')->all();
            $standing = EmployeeItem::with('payItem')->whereIn('employee_id', $employees->pluck('id'))->get()->groupBy('employee_id');
            $inputs = RunInput::with('payItem')->where('payroll_run_id', $run->id)->get()->groupBy('employee_id');
            $fx = fn (string $from, string $to) => $this->convert(1.0, $from, $to, $run->pay_date);

            $run->payslips()->delete();
            foreach ($employees as $employee) {
                $items = ($standing[$employee->id] ?? collect())->filter(fn ($i) => $i->payItem?->status && $i->activeIn($run->period_start, $run->period_end))->values();
                $rows = $this->calculator->calculate($employee, $run->period_start, $run->period_end, $items, $inputs[$employee->id] ?? collect(), $settings, $fx);
                foreach ($rows as $currency => $row) {
                    $payslip = new Payslip(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'currency' => $currency]
                        + array_intersect_key($row, array_flip(['gross', 'taxable', 'paye', 'aids_levy', 'nssa_employee', 'nssa_employer', 'other_deductions', 'employer_levies', 'net']))
                        + ['breakdown' => $row['breakdown'] + ['share' => $row['share'], 'fx_rate' => $row['fx_rate'], 'proration' => $row['proration']]]);
                    $payslip->company_id = $run->company_id;
                    $payslip->save();
                }
            }

            $this->refreshTotals($run, $employees->count());

            return $run->fresh();
        });
    }

    /** Approved overtime dated in the period and not yet paid becomes hourly inputs on the run. */
    private function syncApprovedOvertime(PayrollRun $run, Collection $employees): void
    {
        RunInput::where('payroll_run_id', $run->id)->whereNotNull('overtime_request_id')->delete();
        $requests = \App\Modules\Payroll\Models\OvertimeRequest::where('status', 'approved')->whereNull('payroll_run_id')->whereNotNull('pay_item_id')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('work_date', '>=', $run->period_start->toDateString())->whereDate('work_date', '<=', $run->period_end->toDateString())->get();
        foreach ($requests as $request) {
            $run->inputs()->create(['company_id' => $run->company_id, 'employee_id' => $request->employee_id, 'pay_item_id' => $request->pay_item_id,
                'quantity' => $request->hours, 'amount' => null, 'overtime_request_id' => $request->id]);
        }
    }

    private function refreshTotals(PayrollRun $run, int $employeeCount): void
    {
        $totals = [];
        $costBase = 0.0;
        foreach ($run->payslips()->get()->groupBy('currency') as $currency => $slips) {
            $sum = fn ($k) => round((float) $slips->sum($k), 2);
            $totals[$currency] = [
                'employees' => $slips->pluck('employee_id')->unique()->count(), 'gross' => $sum('gross'), 'paye' => $sum('paye'), 'aids_levy' => $sum('aids_levy'),
                'nssa_employee' => $sum('nssa_employee'), 'nssa_employer' => $sum('nssa_employer'), 'other_deductions' => $sum('other_deductions'),
                'employer_levies' => $sum('employer_levies'), 'net' => $sum('net'),
            ];
            $cost = $totals[$currency]['gross'] + $totals[$currency]['nssa_employer'] + $totals[$currency]['employer_levies'];
            $costBase += $this->convert($cost, $currency, $this->rates->baseCurrency((int) $run->company_id), $run->pay_date);
        }
        $run->update(['totals' => $totals, 'employee_count' => $employeeCount, 'total_cost_base' => round($costBase, 2), 'calculated_at' => now()]);
    }

    /** Posts the payroll journal for each currency and locks the run. */
    public function approve(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            $run = PayrollRun::lockForUpdate()->findOrFail($run->id);
            $this->assertStatus($run, ['draft']);
            if (!$run->calculated_at || $run->payslips()->doesntExist()) {
                throw ValidationException::withMessages(['run' => 'Calculate the payroll before approving it.']);
            }
            if ($run->payslips()->where('net', '<', 0)->exists()) {
                throw ValidationException::withMessages(['run' => 'Some employees have negative net pay. Fix their deductions and recalculate.']);
            }

            $ids = [];
            foreach ($run->payslips()->get()->groupBy('currency') as $currency => $slips) {
                $ids[] = $this->postPayrollJournal($run, $currency, $slips)->id;
            }
            $run->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'journal_entry_ids' => $ids]);
            // Overtime paid by this run cannot be paid again.
            \App\Modules\Payroll\Models\OvertimeRequest::whereIn('id', $run->inputs()->whereNotNull('overtime_request_id')->pluck('overtime_request_id'))->update(['payroll_run_id' => $run->id]);

            return $run->fresh();
        });
    }

    private function postPayrollJournal(PayrollRun $run, string $currency, Collection $slips): JournalEntry
    {
        $company = (int) $run->company_id;
        $map = fn (string $key) => $this->mappings->account($key, $company)->id;
        $lines = [];
        $add = function (int $accountId, string $description, float $debit, float $credit) use (&$lines) {
            $key = $accountId.'|'.($debit > 0 ? 'd' : 'c').'|'.$description;
            $lines[$key] ??= ['account_id' => $accountId, 'description' => $description, 'debit' => 0.0, 'credit' => 0.0];
            $lines[$key]['debit'] += $debit;
            $lines[$key]['credit'] += $credit;
        };

        foreach ($slips as $slip) {
            foreach ($slip->breakdown['earnings'] ?? [] as $earning) {
                // Earnings with their own account keep their name; the rest are summed as gross pay.
                $add((int) ($earning['account_id'] ?: $map('salaries_expense')), $earning['account_id'] ? $earning['name'] : 'Gross pay', (float) $earning['amount'], 0);
            }
            foreach ($slip->breakdown['deductions'] ?? [] as $deduction) {
                $add((int) ($deduction['account_id'] ?: $map('payroll_deductions')), $deduction['name'], 0, (float) $deduction['amount']);
            }
            foreach ($slip->breakdown['levies'] ?? [] as $levy) {
                $add((int) ($levy['account_id'] ?: $map('payroll_deductions')), $levy['name'], 0, (float) $levy['amount']);
            }
            $add($map('employer_contributions'), 'Employer NSSA and levies', (float) $slip->nssa_employer + (float) $slip->employer_levies, 0);
            $add($map('paye_payable'), 'PAYE and AIDS levy', 0, (float) $slip->paye + (float) $slip->aids_levy);
            $add($map('nssa_payable'), 'NSSA (employee and employer)', 0, (float) $slip->nssa_employee + (float) $slip->nssa_employer);
            $add($map('net_wages'), 'Net wages', 0, (float) $slip->net);
        }

        $entry = JournalEntry::create([
            'entry_number' => 'PAY-'.$run->run_number.'-'.$currency, 'entry_date' => $run->period_end->toDateString(),
            'source_module' => 'payroll', 'source_type' => PayrollRun::class, 'source_id' => $run->id,
            'currency' => $currency, 'memo' => 'Payroll '.$run->run_number.' ('.$run->period_start->format('d M').' – '.$run->period_end->format('d M Y').') '.$currency,
            'status' => 'draft', 'created_by' => auth()->id(),
        ]);
        foreach ($lines as $line) {
            $debit = round($line['debit'], 2);
            $credit = round($line['credit'], 2);
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }
            $entry->lines()->create(['company_id' => $run->company_id, 'account_id' => $line['account_id'], 'description' => $line['description'], 'debit' => $debit, 'credit' => $credit]);
        }

        return $this->journals->post($entry);
    }

    /** Pays net wages: Dr Net wages payable / Cr cash, bank or mobile money, per currency and method. */
    public function markPaid(PayrollRun $run, ?Carbon $date = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $date) {
            $run = PayrollRun::lockForUpdate()->findOrFail($run->id);
            $this->assertStatus($run, ['approved']);
            $date ??= $run->pay_date;
            $company = (int) $run->company_id;
            $ids = $run->journal_entry_ids ?? [];

            foreach ($run->payslips()->with('employee')->get()->groupBy('currency') as $currency => $slips) {
                $entry = JournalEntry::create([
                    'entry_number' => 'PAYNET-'.$run->run_number.'-'.$currency, 'entry_date' => $date->toDateString(),
                    'source_module' => 'payroll', 'source_type' => PayrollRun::class, 'source_id' => $run->id,
                    'currency' => $currency, 'memo' => 'Net pay '.$run->run_number.' '.$currency, 'status' => 'draft', 'created_by' => auth()->id(),
                ]);
                $net = round((float) $slips->sum('net'), 2);
                $entry->lines()->create(['company_id' => $company, 'account_id' => $this->mappings->account('net_wages', $company)->id, 'description' => 'Net wages paid', 'debit' => $net, 'credit' => 0]);
                foreach ($slips->groupBy(fn ($s) => $s->employee->payment_method) as $method => $group) {
                    $entry->lines()->create(['company_id' => $company, 'account_id' => $this->mappings->tenderAccount($method === 'mobile_money' ? 'mobile money' : $method, $company)->id,
                        'description' => 'Net wages ('.str_replace('_', ' ', $method).')', 'debit' => 0, 'credit' => round((float) $group->sum('net'), 2)]);
                }
                $ids[] = $this->journals->post($entry)->id;
            }
            $run->update(['status' => 'paid', 'paid_at' => now(), 'journal_entry_ids' => $ids]);

            return $run->fresh();
        });
    }

    public function cancel(PayrollRun $run): void
    {
        $this->assertStatus($run, ['draft']);
        $run->payslips()->delete();
        $run->update(['status' => 'cancelled']);
    }

    // ---- reports ---------------------------------------------------------------------

    /** Year-to-date totals per currency for an employee, up to and including $run (approved / paid runs). */
    public function yearToDate(Employee $employee, PayrollRun $run): array
    {
        return Payslip::query()->join('payroll_runs as r', 'r.id', '=', 'payroll_payslips.payroll_run_id')
            ->where('payroll_payslips.employee_id', $employee->id)
            ->where(fn ($q) => $q->whereIn('r.status', ['approved', 'paid'])->orWhere('r.id', $run->id))
            ->whereYear('r.period_end', $run->taxYear())->whereDate('r.period_end', '<=', $run->period_end->toDateString())
            ->groupBy('payroll_payslips.currency')
            ->selectRaw('payroll_payslips.currency, SUM(gross) as gross, SUM(taxable) as taxable, SUM(paye) as paye, SUM(aids_levy) as aids_levy, SUM(nssa_employee) as nssa_employee, SUM(net) as net')
            ->get()->keyBy('currency')->map(fn ($r) => array_map(fn ($v) => round((float) $v, 2), $r->only(['gross', 'taxable', 'paye', 'aids_levy', 'nssa_employee', 'net'])))->all();
    }

    /** PAYE schedule rows for the run (per employee and currency). */
    public function payeSchedule(PayrollRun $run): Collection
    {
        return $run->payslips()->with('employee')->get()->map(fn (Payslip $p) => [
            'employee_number' => $p->employee->employee_number, 'name' => $p->employee->name, 'tin' => $p->employee->tin, 'national_id' => $p->employee->national_id,
            'currency' => $p->currency, 'gross' => (float) $p->gross, 'taxable' => (float) $p->taxable, 'paye' => (float) $p->paye,
            'aids_levy' => (float) $p->aids_levy, 'total_tax' => round((float) $p->paye + (float) $p->aids_levy, 2),
        ]);
    }

    public function nssaSchedule(PayrollRun $run): Collection
    {
        return $run->payslips()->with('employee')->get()->map(fn (Payslip $p) => [
            'employee_number' => $p->employee->employee_number, 'name' => $p->employee->name, 'nssa_number' => $p->employee->nssa_number, 'national_id' => $p->employee->national_id,
            'date_of_birth' => $p->employee->date_of_birth?->toDateString(), 'currency' => $p->currency, 'insurable' => (float) ($p->breakdown['insurable'] ?? $p->gross),
            'employee' => (float) $p->nssa_employee, 'employer' => (float) $p->nssa_employer, 'total' => round((float) $p->nssa_employee + (float) $p->nssa_employer, 2),
        ]);
    }

    /** Net pay instructions for the bank / mobile money upload. */
    public function paymentList(PayrollRun $run): Collection
    {
        return $run->payslips()->with('employee')->get()->map(fn (Payslip $p) => [
            'employee_number' => $p->employee->employee_number, 'name' => $p->employee->name, 'method' => $p->employee->payment_method,
            'bank' => $p->employee->bank_name, 'branch' => $p->employee->bank_branch, 'account' => $p->employee->payment_method === 'mobile_money' ? $p->employee->mobile_wallet : $p->employee->account_number,
            'currency' => $p->currency, 'amount' => (float) $p->net, 'reference' => $run->run_number.' '.$p->employee->employee_number,
        ]);
    }

    private function convert(float $amount, string $from, string $to, mixed $date): float
    {
        if ($from === $to) {
            return $amount;
        }
        $company = (int) auth()->user()->company_id;

        return $amount * $this->rates->rate($from, $company, $date) / $this->rates->rate($to, $company, $date);
    }

    private function assertStatus(PayrollRun $run, array $allowed): void
    {
        if (!in_array($run->status, $allowed, true)) {
            throw ValidationException::withMessages(['run' => 'Payroll '.$run->run_number.' is '.(PayrollRun::STATUSES[$run->status] ?? $run->status).'.']);
        }
    }
}

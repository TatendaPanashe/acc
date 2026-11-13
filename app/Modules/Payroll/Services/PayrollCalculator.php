<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayItem;
use App\Modules\Payroll\Models\StatutorySetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calculates one employee's pay for a period. Amounts are set in the employee's salary currency.
 *
 * Mixed salaries follow ZIMRA: "for salaries with both ZWG and USD components use the USD tax tables and
 * apportion the tax due accordingly". The whole remuneration is converted to USD (pay-date rate), taxed once on
 * the USD settings, and every figure is then apportioned to each currency by the pay split (and converted).
 * Single-currency pay is taxed on that currency's own table.
 *
 * Calculation:
 *   gross      = earnings (basic prorated for joiners/leavers by calendar days)
 *   NSSA       = min(gross, ceiling) × employee / employer rate
 *   taxable    = taxable earnings − pre-tax deductions − NSSA (when the settings say NSSA is deductible)
 *   PAYE       = taxable × band rate − band deduct (ZIMRA "rate and deduct" table), less tax credits, not below 0
 *   AIDS levy  = PAYE × AIDS levy rate
 *   levies     = gross × levy rate (employer- or employee-paid)
 *   net        = gross − PAYE − AIDS levy − employee NSSA − other deductions − employee levies
 * All statutory figures come from the company's settings for the tax year and currency.
 */
class PayrollCalculator
{
    /**
     * @param Collection $standing EmployeeItem models (with payItem) active in the period
     * @param Collection $inputs   RunInput models (with payItem) for this employee and run
     * @param array<string, StatutorySetting> $settings by currency (with bands)
     * @param callable(string $from, string $to): float $fx converts 1 unit of $from into $to
     * @return array<string, array> payslip rows by currency
     */
    public function calculate(Employee $employee, Carbon $start, Carbon $end, Collection $standing, Collection $inputs, array $settings, callable $fx): array
    {
        $proration = $this->proration($employee, $start, $end);
        $basic = round((float) $employee->basic_salary * $proration, 2);

        $lines = [[
            'code' => 'BASIC', 'name' => 'Basic salary'.($proration < 1 ? ' ('.round($proration * 100, 1).'% of month)' : ''),
            'type' => 'earning', 'category' => 'basic', 'taxable' => true, 'pre_tax' => false, 'account_id' => null, 'amount' => $basic, 'quantity' => null,
        ]];
        foreach ($standing as $item) {
            $lines[] = $this->line($item->payItem, $item->amount, $item->quantity, $basic);
        }
        foreach ($inputs as $input) {
            $rate = $standing->firstWhere('pay_item_id', $input->pay_item_id)?->amount;
            $lines[] = $this->line($input->payItem, $input->amount ?? $rate, $input->quantity, $basic);
        }
        $lines = array_values(array_filter($lines, fn ($l) => abs($l['amount']) >= 0.005));

        $split = $employee->currencySplit();
        $taxCurrency = self::taxCurrency($employee);
        $toTax = $taxCurrency === $employee->salary_currency ? 1.0 : $fx($employee->salary_currency, $taxCurrency);
        $full = $this->portion($employee, array_map(fn ($l) => ['amount' => round($l['amount'] * $toTax, 2)] + $l, $lines), $settings[$taxCurrency], $fx);

        if (count($split) === 1) {
            return [$taxCurrency => $full + ['share' => 1.0, 'fx_rate' => 1.0, 'proration' => $proration]];
        }

        $result = [];
        foreach ($split as $currency => $share) {
            $rate = $currency === $taxCurrency ? 1.0 : $fx($taxCurrency, $currency);
            $result[$currency] = $this->apportion($full, $share * $rate) + ['share' => $share, 'fx_rate' => $rate, 'proration' => $proration];
            $result[$currency]['breakdown']['tax_basis'] = 'Taxed on the '.$taxCurrency.' table on total pay and apportioned '.round($share * 100, 2).'% to '.$currency.' (ZIMRA rule for mixed salaries).';
        }

        return $result;
    }

    /** Currency whose table taxes the employee: USD for mixed USD/ZWG salaries, else the pay currency. */
    public static function taxCurrency(Employee $employee): string
    {
        $split = $employee->currencySplit();

        return count($split) > 1 && isset($split['USD']) ? 'USD' : (count($split) > 1 ? $employee->salary_currency : array_key_first($split));
    }

    /** Scales a calculated result by $factor (share × exchange rate); net is recomputed from the parts. */
    private function apportion(array $row, float $factor): array
    {
        $money = fn ($v) => round((float) $v * $factor, 2);
        foreach (['gross', 'taxable', 'paye', 'aids_levy', 'nssa_employee', 'nssa_employer', 'other_deductions', 'employer_levies'] as $key) {
            $row[$key] = $money($row[$key]);
        }
        $row['net'] = round($row['gross'] - $row['paye'] - $row['aids_levy'] - $row['nssa_employee'] - $row['other_deductions'], 2);
        foreach (['earnings', 'deductions', 'levies', 'credits'] as $list) {
            $row['breakdown'][$list] = array_map(fn ($l) => ['amount' => $money($l['amount'])] + $l, $row['breakdown'][$list]);
        }
        $row['breakdown']['paye_before_credits'] = $money($row['breakdown']['paye_before_credits']);
        $row['breakdown']['insurable'] = $money($row['breakdown']['insurable']);

        return $row;
    }

    private function line(PayItem $item, mixed $amount, mixed $quantity, float $basic): array
    {
        $value = (float) ($amount ?? $item->default_amount);
        $total = match ($item->calculation) {
            'percent_of_basic' => $basic * $value / 100,
            'hourly' => (float) ($quantity ?? 0) * $value,
            default => $value,
        };

        return [
            'code' => $item->code, 'name' => $item->name, 'type' => $item->type, 'category' => $item->category,
            'taxable' => (bool) $item->taxable, 'pre_tax' => (bool) $item->pre_tax, 'account_id' => $item->gl_account_id,
            'amount' => round($total, 2), 'quantity' => $item->calculation === 'hourly' ? (float) $quantity : null,
        ];
    }

    private function portion(Employee $employee, array $lines, StatutorySetting $s, callable $fx): array
    {
        // Ceilings and credits may be set in another currency (NSSA's USD ceiling for ZiG payrolls).
        $inSetting = fn (float $amount, ?string $currency) => !$currency || $currency === $s->currency ? $amount : $amount * $fx($currency, $s->currency);
        $earnings = array_values(array_filter($lines, fn ($l) => $l['type'] === 'earning'));
        $deductions = array_values(array_filter($lines, fn ($l) => $l['type'] === 'deduction'));
        $gross = round(array_sum(array_column($earnings, 'amount')), 2);
        $taxableEarnings = array_sum(array_column(array_filter($earnings, fn ($l) => $l['taxable']), 'amount'));
        $preTax = array_sum(array_column(array_filter($deductions, fn ($l) => $l['pre_tax']), 'amount'));

        $ceiling = $s->nssa_ceiling !== null ? $inSetting((float) $s->nssa_ceiling, $s->nssa_ceiling_currency) : null;
        $insurable = $ceiling !== null ? min($gross, $ceiling) : $gross;
        $nssaEmployee = round($insurable * (float) $s->nssa_employee_rate / 100, 2);
        $nssaEmployer = round($insurable * (float) $s->nssa_employer_rate / 100, 2);

        $taxable = round(max(0, $taxableEarnings - $preTax - ($s->nssa_deductible ? $nssaEmployee : 0)), 2);
        [$payeBefore, $band] = $this->bandTax($taxable, $s);

        $credits = [];
        foreach ($s->credits ?? [] as $credit) {
            if (in_array($credit['code'] ?? null, $employee->tax_credits ?? [], true)) {
                $credits[] = ['name' => $credit['name'], 'amount' => round($inSetting((float) $credit['amount'], $credit['currency'] ?? null), 2)];
            }
        }
        $medical = array_sum(array_column(array_filter($deductions, fn ($l) => $l['category'] === 'medical_aid'), 'amount'));
        if ($medical > 0 && (float) $s->medical_aid_credit_rate > 0) {
            $credits[] = ['name' => 'Medical aid credit', 'amount' => round($medical * (float) $s->medical_aid_credit_rate / 100, 2)];
        }
        $paye = round(max(0, $payeBefore - array_sum(array_column($credits, 'amount'))), 2);
        $aids = round($paye * (float) $s->aids_levy_rate / 100, 2);

        $levies = [];
        $employeeLevies = 0.0;
        $employerLevies = 0.0;
        foreach ($s->levies ?? [] as $levy) {
            $amount = round($gross * (float) ($levy['rate'] ?? 0) / 100, 2);
            if ($amount <= 0) {
                continue;
            }
            $payer = ($levy['payer'] ?? 'employer') === 'employee' ? 'employee' : 'employer';
            $levies[] = ['name' => $levy['name'], 'rate' => (float) $levy['rate'], 'payer' => $payer, 'amount' => $amount, 'account_id' => $levy['account_id'] ?? null];
            $payer === 'employee' ? $employeeLevies += $amount : $employerLevies += $amount;
        }

        $otherDeductions = round(array_sum(array_column($deductions, 'amount')) + $employeeLevies, 2);
        $net = round($gross - $paye - $aids - $nssaEmployee - $otherDeductions, 2);

        return [
            'gross' => $gross, 'taxable' => $taxable, 'paye' => $paye, 'aids_levy' => $aids,
            'nssa_employee' => $nssaEmployee, 'nssa_employer' => $nssaEmployer,
            'other_deductions' => $otherDeductions, 'employer_levies' => round($employerLevies, 2), 'net' => $net,
            'breakdown' => [
                'earnings' => $earnings, 'deductions' => $deductions, 'levies' => $levies, 'credits' => $credits,
                'paye_before_credits' => round($payeBefore, 2), 'band' => $band, 'insurable' => round($insurable, 2), 'tax_currency' => $s->currency,
                'warnings' => $net < 0 ? ['Deductions exceed pay: net pay is negative.'] : [],
            ],
        ];
    }

    /** @return array{0: float, 1: ?array} tax before credits and the band used */
    public function bandTax(float $taxable, StatutorySetting $s): array
    {
        // The band is the highest one starting at or below the income (robust to gaps like 100 / 100.01).
        $band = $s->bands->sortBy(fn ($b) => (float) $b->lower)->filter(fn ($b) => (float) $b->lower <= $taxable)->last();
        if ($band) {
            $tax = max(0, $taxable * (float) $band->rate / 100 - (float) $band->deduct);

            return [round($tax, 2), ['lower' => (float) $band->lower, 'upper' => $band->upper !== null ? (float) $band->upper : null, 'rate' => (float) $band->rate, 'deduct' => (float) $band->deduct]];
        }

        return [0.0, null];
    }

    /** Share of the month the employee was employed (calendar days). */
    public function proration(Employee $employee, Carbon $start, Carbon $end): float
    {
        $from = $employee->start_date->gt($start) ? $employee->start_date : $start;
        $to = $employee->end_date && $employee->end_date->lt($end) ? $employee->end_date : $end;
        $days = $start->diffInDays($end) + 1;
        $worked = max(0, $from->diffInDays($to) + 1);

        return $worked >= $days ? 1.0 : round($worked / $days, 6);
    }
}

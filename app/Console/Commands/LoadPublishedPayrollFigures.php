<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachCompany;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Services\AuditTrailService;
use App\Modules\Payroll\Http\Controllers\PayrollController;
use App\Modules\Payroll\Models\StatutorySetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LoadPublishedPayrollFigures extends Command
{
    use RunsForEachCompany;

    protected $signature = 'payroll:load-published {year : Tax year} {--company= : Only this company id} {--force : Replace settings already saved}';

    protected $description = 'Save the published ZIMRA PAYE tables and NSSA figures (config/payroll_statutory.php) as companies\' payroll statutory settings.';

    public function handle(AuditTrailService $audit): int
    {
        $year = (int) $this->argument('year');

        $this->forEachCompany(function ($company) use ($year, $audit) {
            foreach (Currency::where('status', true)->pluck('code') as $currency) {
                $published = PayrollController::published($year, $currency);
                if (!$published) {
                    continue;
                }
                $existing = StatutorySetting::where('tax_year', $year)->where('currency', $currency)->first();
                if ($existing && !$this->option('force')) {
                    $this->line($company->name.': '.$year.' '.$currency.' already set, skipped (use --force to replace).');
                    continue;
                }
                DB::transaction(function () use ($existing, $year, $currency, $published, $audit) {
                    $setting = $existing ?? new StatutorySetting(['tax_year' => $year, 'currency' => $currency]);
                    $setting->fill(collect($published)->only(['nssa_employee_rate', 'nssa_employer_rate', 'nssa_ceiling', 'nssa_deductible', 'aids_levy_rate', 'medical_aid_credit_rate', 'credits', 'levies'])->all()
                        + ['nssa_ceiling_currency' => $published['nssa_ceiling_currency'] !== $currency ? $published['nssa_ceiling_currency'] : null]);
                    $setting->credits = collect($published['credits'])->map(fn ($c) => ($c['currency'] ?? null) === $currency ? array_diff_key($c, ['currency' => 1]) : $c)->all();
                    $setting->save();
                    $setting->bands()->delete();
                    foreach ($published['bands'] as $band) {
                        $setting->bands()->create($band + ['company_id' => $setting->company_id]);
                    }
                    $audit->record('payroll.tax_table.loaded', $setting, [], ['source' => $published['source'], 'set_year' => $published['set_year']]);
                });
                $this->info($company->name.': loaded '.$year.' '.$currency.' ('.$published['label'].').');
            }
        }, $this->option('company') ? (int) $this->option('company') : null);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachCompany;
use App\Modules\Accounting\Services\Annuity\RecurringBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunAnnuityBilling extends Command
{
    use RunsForEachCompany;

    protected $signature = 'annuity:bill {--date= : Invoice everything due up to this date (default today)} {--company= : Only this company id}';

    protected $description = 'Raise invoices for recurring customer contracts that are due (each contract decides whether its invoices are posted).';

    public function handle(RecurringBillingService $billing): int
    {
        $asOf = Carbon::parse($this->option('date') ?: today())->startOfDay();

        $this->forEachCompany(function ($company) use ($billing, $asOf) {
            if ($billing->due($asOf)->isEmpty()) {
                return;
            }
            $run = $billing->run($asOf);
            $this->info($company->name.': '.$run->run_number.' raised '.$run->invoice_count.' invoices ('.number_format((float) $run->total, 2).').');
        }, $this->option('company') ? (int) $this->option('company') : null);

        return self::SUCCESS;
    }
}

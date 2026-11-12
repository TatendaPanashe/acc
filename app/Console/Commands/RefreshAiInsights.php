<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachCompany;
use App\Modules\Accounting\Services\Ai\AnomalyDetectionService;
use App\Modules\Accounting\Services\Ai\ForecastService;
use Illuminate\Console\Command;

class RefreshAiInsights extends Command
{
    use RunsForEachCompany;

    protected $signature = 'ai:refresh {--company= : Only this company id} {--forecasts : Also refit forecasts (default: on the 1st of the month)}';

    protected $description = 'Scan every company\'s transactions for anomalies and refresh monthly forecasts.';

    public function handle(AnomalyDetectionService $anomalies, ForecastService $forecasts): int
    {
        $refit = $this->option('forecasts') || now()->day === 1;

        $this->forEachCompany(function ($company) use ($anomalies, $forecasts, $refit) {
            $scan = $anomalies->detect();
            $line = $company->name.': '.$scan['new'].' new findings';
            if ($refit) {
                $made = collect($forecasts->generate())->filter(fn ($r) => isset($r['method']))->count();
                $line .= ', '.$made.' forecasts refreshed';
            }
            $this->info($line.'.');
        }, $this->option('company') ? (int) $this->option('company') : null);

        return self::SUCCESS;
    }
}

<?php

namespace App\Modules\Accounting\Services\Ai;

use App\Modules\Accounting\Models\AiForecast;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\FinancialStatementService;
use App\Modules\Accounting\Services\Reporting\ReportingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly forecasts of revenue, expenses and net cash flow from the posted ledger.
 *
 * Model: Holt's double exponential smoothing (level + trend, damped), with additive monthly seasonality
 * (Holt-Winters) once 24 months of history exist. Smoothing parameters are chosen by grid search on
 * one-step-ahead error. The 80% interval is ±1.28 × RMSE × √h; confidence = 100 − MAPE (bounded 5–95).
 * Runs entirely in PHP: no data leaves the server.
 */
class ForecastService
{
    public const TYPES = ['revenue' => 'Revenue', 'expense' => 'Expenses', 'cash_flow' => 'Net cash flow'];
    private const HISTORY_MONTHS = 36;

    public function __construct(
        private readonly ReportingService $reports,
        private readonly FinancialStatementService $statements,
    ) {
    }

    /** @return array<string, array{labels: string[], values: float[], months: string[]}> */
    public function history(?Carbon $asOf = null): array
    {
        $end = ($asOf ?? now())->copy()->startOfMonth()->subDay(); // last complete month
        $start = $end->copy()->startOfMonth()->subMonths(self::HISTORY_MONTHS - 1);
        $monthly = $this->reports->monthly($start, $end);
        $cash = $this->cashFlowByMonth($start, $end);

        // Drop leading months before the company had any activity.
        $keys = array_keys($monthly);
        $first = collect($keys)->search(fn ($k) => $monthly[$k]['revenue'] != 0.0 || $monthly[$k]['total_expenses'] != 0.0 || ($cash[$k] ?? 0) != 0.0);
        $keys = $first === false ? [] : array_slice($keys, $first);

        $series = fn (callable $value) => ['months' => $keys, 'labels' => array_map(fn ($k) => $monthly[$k]['label'], $keys), 'values' => array_map(fn ($k) => round($value($k), 2), $keys)];

        return [
            'revenue' => $series(fn ($k) => $monthly[$k]['revenue']),
            'expense' => $series(fn ($k) => $monthly[$k]['total_expenses']),
            'cash_flow' => $series(fn ($k) => $cash[$k] ?? 0.0),
        ];
    }

    /** Fits each series and stores forecasts (status "review") for the next $horizon months. */
    public function generate(int $horizon = 6, ?Carbon $asOf = null): array
    {
        $companyId = (int) auth()->user()->company_id;
        $results = [];

        foreach ($this->history($asOf) as $type => $series) {
            $values = $series['values'];
            if (count($values) < 4) {
                $results[$type] = ['skipped' => 'Needs at least 4 months of posted history; '.count($values).' available.'];
                continue;
            }
            $fit = self::fit($values, $horizon);
            $lastMonth = Carbon::createFromFormat('Y-m', end($series['months']))->startOfMonth();

            DB::transaction(function () use ($type, $fit, $lastMonth, $companyId, $series, $horizon) {
                AiForecast::where('type', $type)->where('model_key', 'like', 'holt%')->whereDate('forecast_date', '>', $lastMonth->toDateString())->delete();
                foreach ($fit['forecast'] as $h => $value) {
                    $forecast = new AiForecast([
                        'model_key' => $fit['method'], 'type' => $type, 'source_module' => 'general_ledger',
                        'forecast_date' => $lastMonth->copy()->addMonthsNoOverflow($h + 1)->toDateString(),
                        'predicted_value' => round($value, 2),
                        'lower_value' => round($value - $fit['interval'][$h], 2), 'upper_value' => round($value + $fit['interval'][$h], 2),
                        'confidence' => $fit['confidence'], 'status' => 'review',
                        'features' => ['alpha' => $fit['alpha'], 'beta' => $fit['beta'], 'gamma' => $fit['gamma'], 'phi' => $fit['phi'], 'mape' => $fit['mape'], 'rmse' => $fit['rmse'], 'history_months' => count($series['values']), 'horizon' => $horizon],
                    ]);
                    $forecast->company_id = $companyId;
                    $forecast->save();
                }
            });
            $results[$type] = ['method' => $fit['method'], 'confidence' => $fit['confidence'], 'months' => count($values)];
        }

        return $results;
    }

    /**
     * Fits damped Holt (or Holt-Winters with 12-month additive seasonality when ≥ 24 points).
     * @param float[] $y
     */
    public static function fit(array $y, int $horizon): array
    {
        $y = array_values(array_map('floatval', $y));
        $n = count($y);
        $seasonal = $n >= 24;
        $m = 12;
        $best = null;

        foreach ([0.1, 0.2, 0.3, 0.5, 0.7, 0.9] as $alpha) {
            foreach ([0.0, 0.05, 0.1, 0.2, 0.4] as $beta) {
                foreach ($seasonal ? [0.05, 0.1, 0.3] : [0.0] as $gamma) {
                    foreach ([0.9, 0.98] as $phi) {
                        $run = self::smooth($y, $alpha, $beta, $gamma, $phi, $seasonal ? $m : 0);
                        if ($best === null || $run['sse'] < $best['sse']) {
                            $best = $run + compact('alpha', 'beta', 'gamma', 'phi');
                        }
                    }
                }
            }
        }

        $errors = $best['errors'];
        $rmse = $errors ? sqrt(array_sum(array_map(fn ($e) => $e * $e, $errors)) / count($errors)) : 0.0;
        $actuals = array_slice($y, $n - count($errors));
        $ape = [];
        foreach ($errors as $i => $e) {
            if (abs($actuals[$i]) > 1e-6) {
                $ape[] = abs($e / $actuals[$i]);
            }
        }
        $mape = $ape ? array_sum($ape) / count($ape) * 100 : 100.0;

        $forecast = [];
        $interval = [];
        $damp = 0.0;
        for ($h = 1; $h <= $horizon; $h++) {
            $damp += $best['phi'] ** $h;
            $season = $seasonal ? $best['season'][($n + $h - 1) % $m] : 0.0;
            $forecast[] = $best['level'] + $damp * $best['trend'] + $season;
            $interval[] = 1.28 * $rmse * sqrt($h);
        }

        return [
            'method' => $seasonal ? 'holt_winters' : 'holt_linear',
            'alpha' => $best['alpha'], 'beta' => $best['beta'], 'gamma' => $best['gamma'], 'phi' => $best['phi'],
            'forecast' => $forecast, 'interval' => $interval,
            'rmse' => round($rmse, 2), 'mape' => round($mape, 1),
            'confidence' => round(max(5.0, min(95.0, 100 - $mape)), 1),
        ];
    }

    private static function smooth(array $y, float $alpha, float $beta, float $gamma, float $phi, int $m): array
    {
        $n = count($y);
        $season = array_fill(0, max($m, 1), 0.0);
        if ($m) {
            $firstYear = array_slice($y, 0, $m);
            $mean = array_sum($firstYear) / $m;
            foreach ($firstYear as $i => $v) {
                $season[$i] = $v - $mean;
            }
            $level = $mean;
            $trend = (array_sum(array_slice($y, $m, $m)) - array_sum($firstYear)) / ($m * $m);
            $start = $m;
        } else {
            $level = $y[0];
            $trend = $n > 1 ? $y[1] - $y[0] : 0.0;
            $start = 1;
        }

        $sse = 0.0;
        $errors = [];
        for ($t = $start; $t < $n; $t++) {
            $s = $m ? $season[$t % $m] : 0.0;
            $prediction = $level + $phi * $trend + $s;
            $error = $y[$t] - $prediction;
            $errors[] = $error;
            $sse += $error * $error;

            $previousLevel = $level;
            $level = $alpha * ($y[$t] - $s) + (1 - $alpha) * ($level + $phi * $trend);
            $trend = $beta * ($level - $previousLevel) + (1 - $beta) * $phi * $trend;
            if ($m) {
                $season[$t % $m] = $gamma * ($y[$t] - $level) + (1 - $gamma) * $s;
            }
        }

        return compact('level', 'trend', 'season', 'sse', 'errors');
    }

    /** Net change in cash & bank accounts per month. */
    private function cashFlowByMonth(Carbon $start, Carbon $end): array
    {
        $ids = $this->statements->cashAccountIds();
        if (!$ids) {
            return [];
        }

        return JournalLine::query()
            ->join('accounting_journal_entries as e', 'e.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->where('e.status', 'posted')->whereIn('accounting_journal_lines.account_id', $ids)
            ->whereDate('e.entry_date', '>=', $start->toDateString())->whereDate('e.entry_date', '<=', $end->toDateString())
            ->groupBy('e.entry_date')
            ->selectRaw('e.entry_date as day, SUM(accounting_journal_lines.debit) - SUM(accounting_journal_lines.credit) as net')
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->day)->format('Y-m'))
            ->map(fn ($rows) => round((float) $rows->sum('net'), 2))
            ->all();
    }
}

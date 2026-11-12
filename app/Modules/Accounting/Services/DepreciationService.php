<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\DepreciationRun;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly depreciation of the fixed asset register.
 *
 *  straight_line:      (cost - salvage) / useful life months
 *  declining_balance:  net book value × 2 / useful life months (double declining)
 *
 * Depreciation starts in the month of acquisition and never takes net book value below
 * salvage value. A run covers every month not yet depreciated up to the period end, so a
 * missed month is caught up in the next run.
 */
class DepreciationService
{
    public function __construct(
        private readonly JournalPostingService $journalPosting,
        private readonly AccountMappingService $mappings,
        private readonly ExchangeRateService $rates,
    ) {
    }

    /** @return Collection<int, array{asset: FixedAsset, months: int, amount: float, nbv_after: float}> */
    public function preview(AccountingPeriod $period): Collection
    {
        return FixedAsset::where('status', 'active')
            ->whereDate('acquired_on', '<=', $period->ends_on)
            ->orderBy('asset_number')
            ->get()
            ->map(fn (FixedAsset $asset) => $this->forAsset($asset, $period->ends_on))
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->values();
    }

    /** @return array{asset: FixedAsset, months: int, amount: float, nbv_after: float} */
    public function forAsset(FixedAsset $asset, Carbon $periodEnd): array
    {
        $start = $asset->depreciated_to
            ? $asset->depreciated_to->copy()->addDay()->startOfMonth()
            : $asset->acquired_on->copy()->startOfMonth();
        $end = $periodEnd->copy()->startOfMonth();

        $nbv = $asset->netBookValue();
        $floor = (float) $asset->salvage_value;
        $life = max(1, (int) $asset->useful_life_months);
        $straight = round(((float) $asset->cost - $floor) / $life, 2);
        $months = 0;
        $total = 0.0;

        for ($month = $start->copy(); $month->lte($end) && $nbv > $floor; $month->addMonth()) {
            $charge = $asset->depreciation_method === 'declining_balance'
                ? round($nbv * 2 / $life, 2)
                : $straight;
            $charge = min($charge, round($nbv - $floor, 2));

            if ($charge <= 0) {
                break;
            }

            $nbv = round($nbv - $charge, 2);
            $total += $charge;
            $months++;
        }

        return ['asset' => $asset, 'months' => $months, 'amount' => round($total, 2), 'nbv_after' => $nbv];
    }

    public function run(AccountingPeriod $period): DepreciationRun
    {
        return DB::transaction(function () use ($period) {
            if (DepreciationRun::where('accounting_period_id', $period->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['period' => 'Depreciation has already been run for '.$period->name.'.']);
            }

            // Lock the register so two runs cannot depreciate the same asset.
            FixedAsset::where('status', 'active')->lockForUpdate()->get();
            $rows = $this->preview($period);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'No assets need depreciation up to '.$period->ends_on->format('d M Y').'.']);
            }

            $company = (int) $period->company_id;
            $total = round($rows->sum('amount'), 2);

            $entry = new JournalEntry([
                'entry_number' => 'DEP-'.$period->ends_on->format('Ym'),
                'entry_date' => $period->ends_on,
                'source_module' => 'fixed_assets',
                'currency' => $this->rates->baseCurrency($company),
                'exchange_rate' => 1,
                'memo' => 'Depreciation for '.$period->name,
                'created_by' => auth()->id(),
            ]);
            $entry->company_id = $company;
            $entry->save();

            $expense = $this->mappings->account('depreciation_expense', $company);
            $accumulated = $this->mappings->account('accumulated_depreciation', $company);
            foreach ([[$expense, $total, 0], [$accumulated, 0, $total]] as [$account, $debit, $credit]) {
                $entry->lines()->create([
                    'company_id' => $company, 'account_id' => $account->id, 'description' => 'Depreciation '.$period->name,
                    'debit' => $debit, 'credit' => $credit, 'fx_debit' => $debit, 'fx_credit' => $credit,
                ]);
            }

            $this->journalPosting->post($entry);

            $run = DepreciationRun::create([
                'accounting_period_id' => $period->id,
                'run_date' => $period->ends_on,
                'total' => $total,
                'journal_entry_id' => $entry->id,
                'created_by' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                $run->lines()->create(['company_id' => $company, 'fixed_asset_id' => $row['asset']->id, 'months' => $row['months'], 'amount' => $row['amount']]);
                $row['asset']->update([
                    'accumulated_depreciation' => round((float) $row['asset']->accumulated_depreciation + $row['amount'], 2),
                    'depreciated_to' => $period->ends_on,
                ]);
            }

            return $run;
        });
    }
}

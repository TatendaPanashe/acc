<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Finds the accounting period for a date and enforces that only open periods accept postings.
 */
class PeriodService
{
    public function periodFor(mixed $date, int $companyId): ?AccountingPeriod
    {
        $day = Carbon::parse($date)->toDateString();

        return AccountingPeriod::withoutGlobalScopes()
            ->with(['fiscalYear' => fn ($query) => $query->withoutGlobalScopes()])
            ->where('company_id', $companyId)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->orderBy('starts_on')
            ->first();
    }

    /** Returns the open period covering the date, or throws a validation error explaining why posting is not allowed. */
    public function openPeriodFor(mixed $date, int $companyId): AccountingPeriod
    {
        $day = Carbon::parse($date);
        $period = $this->periodFor($day, $companyId);

        if (!$period) {
            throw ValidationException::withMessages([
                'period' => 'No accounting period covers '.$day->format('d M Y').'. Ask the Company Admin to create the fiscal year and periods.',
            ]);
        }

        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'period' => 'Accounting period '.$period->name.' is '.$period->status.'. Postings dated '.$day->format('d M Y').' are not allowed.',
            ]);
        }

        if ($period->fiscalYear && $period->fiscalYear->status !== 'open') {
            throw ValidationException::withMessages([
                'period' => 'Fiscal year '.$period->fiscalYear->name.' is '.$period->fiscalYear->status.'. Postings are not allowed.',
            ]);
        }

        return $period;
    }

    public function fiscalYearFor(mixed $date, int $companyId): ?FiscalYear
    {
        $day = Carbon::parse($date)->toDateString();

        return FiscalYear::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->first();
    }
}

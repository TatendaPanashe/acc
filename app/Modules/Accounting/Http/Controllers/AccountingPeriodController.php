<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AuditTrailService;
use App\Rules\TenantExists;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingPeriodController extends Controller
{
    public function index(): View
    {
        return view('accounting.gl.periods.index', [
            'periods' => AccountingPeriod::with('fiscalYear')->orderByDesc('starts_on')->paginate(24),
            'fiscalYears' => FiscalYear::where('status', '!=', 'closed')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AccountingPeriod::create($this->validated($request));

        return back()->with('success', 'Accounting period created.');
    }

    public function edit(AccountingPeriod $period): View
    {
        return view('accounting.gl.periods.edit', [
            'period' => $period,
            'fiscalYears' => FiscalYear::where('status', '!=', 'closed')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function update(Request $request, AccountingPeriod $period, AuditTrailService $audit): RedirectResponse
    {
        $data = $this->validated($request, $period);

        $datesChanged = $data['starts_on'] !== $period->starts_on->toDateString() || $data['ends_on'] !== $period->ends_on->toDateString();
        if ($datesChanged && $this->postedEntries($period)->exists()) {
            throw ValidationException::withMessages(['starts_on' => 'The dates of a period that already has posted entries cannot be changed.']);
        }

        $old = $period->only(['name', 'starts_on', 'ends_on', 'status']);
        $period->update($data);

        if ($old['status'] !== $period->status) {
            $audit->record('period.status_changed', $period, ['status' => $old['status']], ['status' => $period->status], $request);
        }

        return redirect()->route('accounting.gl.periods.index')->with('success', 'Accounting period updated.');
    }

    /** Closes the period (finance manager / company admin). */
    public function destroy(Request $request, AccountingPeriod $period, AuditTrailService $audit): RedirectResponse
    {
        $drafts = JournalEntry::where('status', '!=', 'posted')
            ->whereDate('entry_date', '>=', $period->starts_on)
            ->whereDate('entry_date', '<=', $period->ends_on)
            ->count();

        if ($drafts > 0) {
            return back()->with('error', "Period {$period->name} has {$drafts} unposted journal ".str('entry')->plural($drafts).'. Post or delete them before closing.');
        }

        $old = $period->status;
        $period->update(['status' => 'closed']);
        $audit->record('period.status_changed', $period, ['status' => $old], ['status' => 'closed'], $request);

        return back()->with('success', 'Accounting period closed.');
    }

    private function postedEntries(AccountingPeriod $period)
    {
        return JournalEntry::where('status', 'posted')
            ->where(fn ($query) => $query->where('accounting_period_id', $period->id)
                ->orWhereBetween('entry_date', [$period->starts_on->toDateString(), $period->ends_on->toDateString()]));
    }

    private function validated(Request $request, ?AccountingPeriod $period = null): array
    {
        $data = $request->validate([
            'fiscal_year_id' => ['required', new TenantExists('accounting_fiscal_years')],
            'name' => ['required', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['open', 'locked', 'closed'])],
        ]);

        $data['starts_on'] = \Illuminate\Support\Carbon::parse($data['starts_on'])->toDateString();
        $data['ends_on'] = \Illuminate\Support\Carbon::parse($data['ends_on'])->toDateString();

        $fiscalYear = FiscalYear::findOrFail($data['fiscal_year_id']);
        if ($data['starts_on'] < $fiscalYear->starts_on->toDateString() || $data['ends_on'] > $fiscalYear->ends_on->toDateString()) {
            throw ValidationException::withMessages(['starts_on' => 'The period must fall inside fiscal year '.$fiscalYear->name.'.']);
        }

        $overlaps = AccountingPeriod::when($period, fn ($query) => $query->whereKeyNot($period->id))
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->first();
        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => 'These dates overlap period '.$overlaps->name.'.']);
        }

        return $data;
    }
}

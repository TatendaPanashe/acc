<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\FiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    public function index(): View
    {
        return view('accounting.gl.fiscal_years.index', [
            'fiscalYears' => FiscalYear::withCount('periods')->orderByDesc('starts_on')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        FiscalYear::create($this->validated($request));

        return back()->with('success', 'Fiscal year created.');
    }

    public function edit(FiscalYear $fiscalYear): View
    {
        return view('accounting.gl.fiscal_years.edit', compact('fiscalYear'));
    }

    public function update(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        $fiscalYear->update($this->validated($request, $fiscalYear));

        return redirect()->route('accounting.gl.fiscal-years.index')->with('success', 'Fiscal year updated.');
    }

    public function destroy(FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->periods()->exists()) {
            $fiscalYear->update(['status' => 'locked']);

            return back()->with('warning', 'Fiscal year has periods, so it was locked instead of deleted.');
        }

        $fiscalYear->delete();

        return back()->with('success', 'Fiscal year deleted.');
    }

    private function validated(Request $request, ?FiscalYear $fiscalYear = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'max:255',
                Rule::unique('accounting_fiscal_years')
                    ->where('company_id', $request->user()->company_id)
                    ->ignore($fiscalYear),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['open', 'locked', 'closed'])],
        ]);
    }
}

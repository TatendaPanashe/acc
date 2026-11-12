<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(): View
    {
        return view('accounting.gl.budgets.index', [
            'budgets' => Budget::with(['account', 'period'])->latest()->paginate(30),
            'accounts' => Account::where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
            'periods' => AccountingPeriod::where('status', 'open')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Budget::updateOrCreate(
            $request->validate([
                'accounting_period_id' => ['required', new TenantExists('accounting_periods')],
                'account_id' => ['required', new TenantExists('accounting_accounts')],
            ]),
            $request->validate([
                'currency' => ['required', 'size:3', TenantExists::currency()],
                'amount' => ['required', 'numeric'],
                'notes' => ['nullable', 'max:1000'],
            ])
        );

        return back()->with('success', 'Budget saved.');
    }

    public function edit(Budget $budget): View
    {
        return view('accounting.gl.budgets.edit', [
            'budget' => $budget,
            'accounts' => Account::where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
            'periods' => AccountingPeriod::where('status', 'open')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $data = $request->validate([
            'accounting_period_id' => ['required', new TenantExists('accounting_periods')],
            'account_id' => ['required', new TenantExists('accounting_accounts')],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'amount' => ['required', 'numeric'],
            'notes' => ['nullable', 'max:1000'],
        ]);

        $budget->update($data);

        return redirect()->route('accounting.gl.budgets.index')->with('success', 'Budget updated.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $budget->delete();

        return back()->with('success', 'Budget deleted.');
    }
}

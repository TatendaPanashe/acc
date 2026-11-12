<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    public function index(): View
    {
        return view('accounting.gl.accounts.index', [
            'accounts' => Account::orderBy('code')->paginate(25),
            'parentAccounts' => Account::where('status', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'max:40', Rule::unique('accounting_accounts')->where('company_id', $request->user()->company_id)],
            'name' => ['required', 'max:255'],
            'parent_id' => ['nullable', new TenantExists('accounting_accounts')],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'is_control_account' => ['nullable', 'boolean'],
            'allow_posting' => ['nullable', 'boolean'],
        ]);

        Account::create($data + [
            'is_control_account' => $request->boolean('is_control_account'),
            'allow_posting' => $request->boolean('allow_posting', true),
            'status' => true,
        ]);

        return back()->with('success', 'Account created.');
    }

    public function edit(Account $account): View
    {
        return view('accounting.gl.accounts.edit', [
            'account' => $account,
            'parentAccounts' => Account::whereKeyNot($account->id)->where('status', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'code' => [
                'required',
                'max:40',
                Rule::unique('accounting_accounts')->where('company_id', $request->user()->company_id)->ignore($account),
            ],
            'name' => ['required', 'max:255'],
            'parent_id' => ['nullable', new TenantExists('accounting_accounts')],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'is_control_account' => ['nullable', 'boolean'],
            'allow_posting' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        $account->update($data + [
            'is_control_account' => $request->boolean('is_control_account'),
            'allow_posting' => $request->boolean('allow_posting'),
            'status' => $request->boolean('status'),
        ]);

        return redirect()->route('accounting.gl.accounts.index')->with('success', 'Account updated.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->journalLines()->exists()) {
            $account->update(['status' => false, 'allow_posting' => false]);

            return back()->with('warning', 'Account has ledger activity, so it was deactivated instead of deleted.');
        }

        $account->delete();

        return back()->with('success', 'Account deleted.');
    }
}

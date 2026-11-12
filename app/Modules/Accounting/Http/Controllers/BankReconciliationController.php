<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyBankAccount;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankStatement;
use App\Modules\Accounting\Models\BankStatementLine;
use App\Modules\Accounting\Services\BankReconciliationService;
use App\Rules\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly BankReconciliationService $reconciliation)
    {
    }

    public function index(): View
    {
        return view('accounting.banking.index', [
            'bankAccounts' => CompanyBankAccount::with('glAccount')->orderByDesc('is_primary')->orderBy('bank_name')->get(),
            'statements' => BankStatement::with('bankAccount')->withCount(['lines', 'lines as unmatched_count' => fn ($q) => $q->whereNull('journal_line_id')])
                ->latest('statement_date')->paginate(20),
            'assetAccounts' => Account::where('type', 'asset')->where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
        ]);
    }

    public function linkAccount(Request $request, CompanyBankAccount $bankAccount): RedirectResponse
    {
        $data = $request->validate([
            'gl_account_id' => ['required', (new TenantExists('accounting_accounts'))->where('type', 'asset')->where('allow_posting', true)],
        ]);
        $bankAccount->update($data);

        return back()->with('success', $bankAccount->label().' linked to its GL account.');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_bank_account_id' => ['required', new TenantExists('company_bank_accounts')],
            'statement_date' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric'],
            'closing_balance' => ['required', 'numeric'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $statement = $this->reconciliation->import(
            CompanyBankAccount::findOrFail($data['company_bank_account_id']),
            $request->file('file')->getRealPath(),
            $data['statement_date'],
            (float) $data['opening_balance'],
            (float) $data['closing_balance'],
        );
        $matched = $this->reconciliation->autoMatch($statement);

        return redirect()->route('accounting.banking.statements.show', $statement)
            ->with('success', $statement->lines()->count().' transactions imported; '.$matched.' matched automatically.');
    }

    public function show(BankStatement $statement): View
    {
        $statement->load(['bankAccount', 'lines.journalLine.journalEntry']);

        return view('accounting.banking.show', [
            'statement' => $statement,
            'summary' => $this->reconciliation->summary($statement),
            'candidates' => $statement->isReconciled()
                ? collect()
                : $statement->lines->whereNull('journal_line_id')->mapWithKeys(fn ($line) => [$line->id => $this->reconciliation->candidates($line)]),
            'counterAccounts' => Account::where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
            'reconciliation' => $this->reconciliation,
        ]);
    }

    public function autoMatch(BankStatement $statement): RedirectResponse
    {
        $matched = $this->reconciliation->autoMatch($statement);

        return back()->with('success', $matched.' '.str('line')->plural($matched).' matched automatically.');
    }

    public function match(Request $request, BankStatementLine $line): RedirectResponse
    {
        $data = $request->validate(['journal_line_id' => ['required', new TenantExists('accounting_journal_lines')]]);
        $this->reconciliation->match($line, (int) $data['journal_line_id']);

        return back()->with('success', 'Statement line matched.');
    }

    public function unmatch(BankStatementLine $line): RedirectResponse
    {
        $this->reconciliation->unmatch($line);

        return back()->with('success', 'Match removed.');
    }

    public function createEntry(Request $request, BankStatementLine $line): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
            'memo' => ['nullable', 'max:255'],
        ]);
        $entry = $this->reconciliation->createEntry($line, Account::findOrFail($data['account_id']), $data['memo'] ?? null);

        return back()->with('success', 'Journal '.$entry->entry_number.' posted and matched.');
    }

    public function complete(BankStatement $statement): RedirectResponse
    {
        $this->reconciliation->complete($statement);

        return back()->with('success', 'Bank reconciliation completed.');
    }

    public function destroy(BankStatement $statement): RedirectResponse
    {
        abort_if($statement->isReconciled(), 422, 'A reconciled statement cannot be deleted.');
        $statement->delete();

        return redirect()->route('accounting.banking.index')->with('success', 'Statement deleted. Journals posted from it remain in the ledger.');
    }
}

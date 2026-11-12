<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Accounting\Services\PeriodService;
use App\Rules\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function index(): View
    {
        return view('accounting.gl.journals.index', [
            'journals' => JournalEntry::with('lines')->latest('entry_date')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('accounting.gl.journals.create', [
            'journal' => new JournalEntry(['entry_date' => now(), 'currency' => 'USD']),
            'accounts' => Account::where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
            'periods' => AccountingPeriod::where('status', 'open')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $entry = DB::transaction(function () use ($data, $request) {
            $entry = JournalEntry::create([
                'entry_number' => $data['entry_number'],
                'accounting_period_id' => $this->periodId($data['entry_date'], $request),
                'entry_date' => $data['entry_date'],
                'currency' => strtoupper($data['currency']),
                'memo' => $data['memo'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->createLines($entry, $data['lines'], $request->user()->company_id);

            return $entry;
        });

        return redirect()->route('accounting.gl.journals.show', $entry)->with('success', 'Journal entry saved as draft.');
    }

    public function show(JournalEntry $journal): View
    {
        $journal->load(['lines.account']);

        return view('accounting.gl.journals.show', compact('journal'));
    }

    public function edit(JournalEntry $journal): View|RedirectResponse
    {
        if ($journal->status === 'posted') {
            return redirect()->route('accounting.gl.journals.show', $journal)
                ->with('warning', 'Posted journals cannot be edited. Create a reversing journal instead.');
        }

        $journal->load('lines');

        return view('accounting.gl.journals.edit', [
            'journal' => $journal,
            'accounts' => Account::where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
            'periods' => AccountingPeriod::where('status', 'open')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function update(Request $request, JournalEntry $journal): RedirectResponse
    {
        abort_if($journal->status === 'posted', 422, 'Posted journals cannot be edited.');

        $data = $this->validated($request, $journal);

        DB::transaction(function () use ($data, $request, $journal) {
            $journal->update([
                'entry_number' => $data['entry_number'],
                'accounting_period_id' => $this->periodId($data['entry_date'], $request),
                'entry_date' => $data['entry_date'],
                'currency' => strtoupper($data['currency']),
                'memo' => $data['memo'] ?? null,
            ]);

            $journal->lines()->delete();
            $this->createLines($journal, $data['lines'], $request->user()->company_id);
        });

        return redirect()->route('accounting.gl.journals.show', $journal)->with('success', 'Journal entry updated.');
    }

    public function destroy(JournalEntry $journal): RedirectResponse
    {
        abort_if($journal->status === 'posted', 422, 'Posted journals cannot be deleted.');

        $journal->delete();

        return redirect()->route('accounting.gl.journals.index')->with('success', 'Draft journal deleted.');
    }

    public function post(JournalPostingService $postingService, JournalEntry $journal): RedirectResponse
    {
        $postingService->post($journal);

        return back()->with('success', 'Journal entry posted.');
    }

    private function validated(Request $request, ?JournalEntry $journal = null): array
    {
        return $request->validate([
            'entry_number' => [
                'required',
                'max:60',
                Rule::unique('accounting_journal_entries')
                    ->where('company_id', $request->user()->company_id)
                    ->ignore($journal),
            ],
            'entry_date' => ['required', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'memo' => ['nullable', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
            'lines.*.description' => ['nullable', 'max:1000'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function periodId(string $date, Request $request): ?int
    {
        return app(PeriodService::class)->periodFor($date, $request->user()->company_id)?->id;
    }

    private function createLines(JournalEntry $entry, array $lines, int $companyId): void
    {
        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit <= 0 && $credit <= 0) {
                continue;
            }

            $entry->lines()->create([
                'company_id' => $companyId,
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }
    }
}

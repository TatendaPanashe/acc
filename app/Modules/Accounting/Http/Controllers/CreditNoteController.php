<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Services\CommercialPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreditNoteController extends Controller
{
    public function index(): View
    {
        return view('accounting.ar.credit_notes.index', [
            'creditNotes' => CreditNote::with(['customer', 'invoice'])->latest('credit_note_date')->paginate(25),
            'customers' => Customer::where('status', true)->orderBy('name')->get(),
            'invoices' => SalesInvoice::whereIn('status', ['posted', 'part_paid'])->orderByDesc('invoice_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CreditNote::create($this->validated($request));
        return back()->with('success', 'Credit note saved as draft.');
    }

    public function post(CommercialPostingService $postingService, CreditNote $creditNote): RedirectResponse
    {
        $postingService->postCreditNote($creditNote);
        return back()->with('success', 'Credit note posted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => ['required', new TenantExists('accounting_customers')],
            'invoice_id' => ['nullable', new TenantExists('accounting_invoices')],
            'credit_note_number' => ['required', 'max:60', Rule::unique('accounting_credit_notes')->where('company_id', $request->user()->company_id)],
            'credit_note_date' => ['required', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'subtotal' => ['required', 'numeric', 'min:0.01'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'max:1000'],
        ]);
        $data['tax_total'] = round((float) $data['subtotal'] * (float) ($data['tax_rate'] ?? 0) / 100, 2);
        $data['total'] = round((float) $data['subtotal'] + $data['tax_total'], 2);
        return $data;
    }
}

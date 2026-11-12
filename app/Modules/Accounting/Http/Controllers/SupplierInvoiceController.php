<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Services\CommercialPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SupplierInvoiceController extends Controller
{
    public function index(): View
    {
        return view('accounting.ap.invoices.index', [
            'invoices' => SupplierInvoice::with('supplier')->latest('invoice_date')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('accounting.ap.invoices.create', $this->formData(new SupplierInvoice(['invoice_date' => now(), 'due_date' => now()->addDays(30), 'currency' => 'USD'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(fn () => $this->saveInvoice(new SupplierInvoice(), $data, $request->user()->company_id));
        return redirect()->route('accounting.ap.supplier-invoices.show', $invoice)->with('success', 'Supplier invoice saved.');
    }

    public function show(SupplierInvoice $supplierInvoice): View
    {
        $supplierInvoice->load(['supplier', 'lines.expenseAccount']);
        return view('accounting.ap.invoices.show', ['invoice' => $supplierInvoice]);
    }

    public function edit(SupplierInvoice $supplierInvoice): View|RedirectResponse
    {
        if ($supplierInvoice->status !== 'draft') return redirect()->route('accounting.ap.supplier-invoices.show', $supplierInvoice)->with('warning', 'Only draft supplier invoices can be edited.');
        $supplierInvoice->load('lines');
        return view('accounting.ap.invoices.edit', $this->formData($supplierInvoice));
    }

    public function update(Request $request, SupplierInvoice $supplierInvoice): RedirectResponse
    {
        abort_if($supplierInvoice->status !== 'draft', 422, 'Only draft supplier invoices can be edited.');
        $data = $this->validated($request, $supplierInvoice);
        DB::transaction(fn () => $this->saveInvoice($supplierInvoice, $data, $request->user()->company_id));
        return redirect()->route('accounting.ap.supplier-invoices.show', $supplierInvoice)->with('success', 'Supplier invoice updated.');
    }

    public function destroy(SupplierInvoice $supplierInvoice): RedirectResponse
    {
        abort_if($supplierInvoice->status !== 'draft', 422, 'Only drafts can be deleted.');
        $supplierInvoice->delete();
        return redirect()->route('accounting.ap.supplier-invoices.index')->with('success', 'Draft supplier invoice deleted.');
    }

    public function post(CommercialPostingService $postingService, SupplierInvoice $supplierInvoice): RedirectResponse
    {
        $postingService->postSupplierInvoice($supplierInvoice);
        return back()->with('success', 'Supplier invoice posted.');
    }

    public function pdf(SupplierInvoice $supplierInvoice): Response
    {
        $supplierInvoice->load(['supplier', 'lines']);

        $pdf = Pdf::loadView('accounting.ap.invoices.pdf', [
            'invoice' => $supplierInvoice,
            'supplier' => $supplierInvoice->supplier,
            'company' => $supplierInvoice->supplier?->company,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('supplier-invoice-'.$supplierInvoice->invoice_number.'.pdf');
    }

    private function formData(SupplierInvoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'suppliers' => Supplier::where('status', true)->orderBy('name')->get(),
            'expenseAccounts' => Account::whereIn('type', ['expense', 'asset'])->where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
        ];
    }

    private function validated(Request $request, ?SupplierInvoice $invoice = null): array
    {
        return $request->validate([
            'supplier_id' => ['required', new TenantExists('accounting_suppliers')],
            'invoice_number' => ['required', 'max:80'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.expense_account_id' => ['required', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
            'lines.*.description' => ['required', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function saveInvoice(SupplierInvoice $invoice, array $data, int $companyId): SupplierInvoice
    {
        $subtotal = 0; $taxTotal = 0;
        foreach ($data['lines'] as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
            $subtotal += $lineTotal;
            $taxTotal += round($lineTotal * (float) ($line['tax_rate'] ?? 0) / 100, 2);
        }
        $invoice->fill([
            'supplier_id' => $data['supplier_id'],
            'invoice_number' => $data['invoice_number'],
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'],
            'currency' => strtoupper($data['currency']),
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
            'status' => 'draft',
        ])->save();
        $invoice->lines()->delete();
        foreach ($data['lines'] as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
            $invoice->lines()->create([...$line, 'company_id' => $companyId, 'line_total' => $lineTotal]);
        }
        return $invoice;
    }
}

<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Services\CommercialPostingService;
use App\Modules\Accounting\Services\ZimraFdmsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SalesInvoiceController extends Controller
{
    public function index(): View
    {
        return view('accounting.ar.invoices.index', [
            'invoices' => SalesInvoice::with('customer')->latest('invoice_date')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('accounting.ar.invoices.create', $this->formData(new SalesInvoice(['invoice_date' => now(), 'due_date' => now()->addDays(30), 'currency' => 'USD'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(fn () => $this->saveInvoice(new SalesInvoice(), $data, $request->user()->company_id));
        return redirect()->route('accounting.ar.invoices.show', $invoice)->with('success', 'Invoice saved as draft.');
    }

    public function show(SalesInvoice $invoice, ZimraFdmsService $zimra): View
    {
        $invoice->load(['customer', 'lines.revenueAccount']);
        $einvoice = $zimra->documentFor($invoice);
        return view('accounting.ar.invoices.show', compact('invoice', 'einvoice'));
    }

    public function edit(SalesInvoice $invoice): View|RedirectResponse
    {
        if ($invoice->status !== 'draft') {
            return redirect()->route('accounting.ar.invoices.show', $invoice)->with('warning', 'Only draft invoices can be edited.');
        }
        $invoice->load('lines');
        return view('accounting.ar.invoices.edit', $this->formData($invoice));
    }

    public function update(Request $request, SalesInvoice $invoice): RedirectResponse
    {
        abort_if($invoice->status !== 'draft', 422, 'Only draft invoices can be edited.');
        $data = $this->validated($request, $invoice);
        DB::transaction(fn () => $this->saveInvoice($invoice, $data, $request->user()->company_id));
        return redirect()->route('accounting.ar.invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function destroy(SalesInvoice $invoice): RedirectResponse
    {
        abort_if($invoice->status !== 'draft', 422, 'Only draft invoices can be deleted.');
        $invoice->delete();
        return redirect()->route('accounting.ar.invoices.index')->with('success', 'Draft invoice deleted.');
    }

    public function post(CommercialPostingService $postingService, SalesInvoice $invoice): RedirectResponse
    {
        $postingService->postSalesInvoice($invoice);
        return back()->with('success', 'Invoice posted to the general ledger.');
    }

    public function submitToZimra(ZimraFdmsService $zimra, SalesInvoice $invoice): RedirectResponse
    {
        try {
            $document = $zimra->submitInvoice($invoice);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Invoice submitted to ZIMRA FDMS. Receipt ID: '.$document->authority_reference);
    }

    public function pdf(SalesInvoice $invoice, ZimraFdmsService $zimra): Response
    {
        $invoice->load(['customer', 'lines']);

        $pdf = Pdf::loadView('accounting.ar.invoices.pdf', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'company' => $invoice->customer?->company,
            'einvoice' => $zimra->documentFor($invoice),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    private function formData(SalesInvoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'customers' => Customer::where('status', true)->orderBy('name')->get(),
            'revenueAccounts' => Account::where('type', 'revenue')->where('allow_posting', true)->where('status', true)->orderBy('code')->get(),
        ];
    }

    private function validated(Request $request, ?SalesInvoice $invoice = null): array
    {
        return $request->validate([
            'customer_id' => ['required', new TenantExists('accounting_customers')],
            'invoice_number' => ['required', 'max:60', Rule::unique('accounting_invoices')->where('company_id', $request->user()->company_id)->ignore($invoice)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.revenue_account_id' => ['required', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
            'lines.*.description' => ['required', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function saveInvoice(SalesInvoice $invoice, array $data, int $companyId): SalesInvoice
    {
        $subtotal = 0; $taxTotal = 0;
        foreach ($data['lines'] as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $subtotal += $lineTotal;
            $taxTotal += round($lineTotal * (float) ($line['tax_rate'] ?? 0) / 100, 2);
        }
        $invoice->fill([
            'customer_id' => $data['customer_id'],
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
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $invoice->lines()->create([...$line, 'company_id' => $companyId, 'line_total' => $lineTotal]);
        }
        return $invoice;
    }
}

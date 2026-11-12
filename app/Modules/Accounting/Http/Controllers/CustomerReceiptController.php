<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerReceipt;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Services\CommercialPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerReceiptController extends Controller
{
    public function index(): View
    {
        return view('accounting.ar.receipts.index', [
            'receipts' => CustomerReceipt::with(['customer', 'invoice', 'allocations.invoice'])->latest('receipt_date')->paginate(25),
            'customers' => Customer::where('status', true)->orderBy('name')->get(),
            'openInvoices' => SalesInvoice::with('customer')->whereIn('status', ['posted', 'part_paid'])->orderBy('due_date')->get(),
        ]);
    }

    public function store(Request $request, CommercialPostingService $postingService): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', new TenantExists('accounting_customers')],
            'invoice_id' => ['nullable', new TenantExists('accounting_invoices')],
            'receipt_number' => ['required', 'max:60', Rule::unique('accounting_customer_receipts')->where('company_id', $request->user()->company_id)],
            'receipt_date' => ['required', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'max:40'],
            'memo' => ['nullable', 'max:1000'],
            'post_now' => ['nullable', 'boolean'],
        ]);

        $receipt = CustomerReceipt::create($data);
        if ($request->boolean('post_now')) {
            $postingService->postReceipt($receipt, $receipt->invoice);
        }

        return back()->with('success', 'Receipt saved.');
    }

    public function post(CommercialPostingService $postingService, CustomerReceipt $receipt): RedirectResponse
    {
        $postingService->postReceipt($receipt, $receipt->invoice);
        return back()->with('success', 'Receipt posted.');
    }
}

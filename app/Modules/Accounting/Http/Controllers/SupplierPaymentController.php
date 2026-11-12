<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Models\SupplierPayment;
use App\Modules\Accounting\Services\CommercialPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function index(): View
    {
        return view('accounting.ap.payments.index', [
            'payments' => SupplierPayment::with(['supplier', 'invoice'])->latest('payment_date')->paginate(25),
            'suppliers' => Supplier::where('status', true)->orderBy('name')->get(),
            'openInvoices' => SupplierInvoice::with('supplier')->whereIn('status', ['posted', 'part_paid'])->orderBy('due_date')->get(),
        ]);
    }

    public function store(Request $request, CommercialPostingService $postingService): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', new TenantExists('accounting_suppliers')],
            'supplier_invoice_id' => ['nullable', new TenantExists('accounting_supplier_invoices')],
            'payment_number' => ['required', 'max:60', Rule::unique('accounting_supplier_payments')->where('company_id', $request->user()->company_id)],
            'payment_date' => ['required', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'max:40'],
            'post_now' => ['nullable', 'boolean'],
        ]);
        $payment = SupplierPayment::create($data);
        // Supplier payments need approval: only approvers may post on capture, and company approval rules
        // (thresholds, segregation of duties) apply as they do on the approval route.
        $policy = app(\App\Modules\Accounting\Services\Approvals\ApprovalPolicy::class);
        $blocked = $request->boolean('post_now') && $request->user()->can('approve') ? $policy->blockReason($request->user(), $payment, 'supplier_payment') : null;
        if ($request->boolean('post_now') && $request->user()->can('approve') && !$blocked) {
            $postingService->postSupplierPayment($payment, isset($data['supplier_invoice_id']) ? SupplierInvoice::find($data['supplier_invoice_id']) : null);
            $policy->record($payment->fresh(), 'supplier_payment', 'approved', $request->user());
        }
        return back()->with('success', $payment->fresh()->status === 'posted' ? 'Supplier payment posted.' : 'Supplier payment saved and awaiting approval.'.($blocked ? ' '.$blocked : ''));
    }

    public function post(CommercialPostingService $postingService, SupplierPayment $payment): RedirectResponse
    {
        $postingService->postSupplierPayment($payment, $payment->invoice);
        return back()->with('success', 'Supplier payment posted.');
    }
}

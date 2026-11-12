<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\PaymentRun;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Models\SupplierPayment;
use App\Modules\Accounting\Services\CommercialPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentRunController extends Controller
{
    public function index(): View
    {
        return view('accounting.ap.payment_runs.index', [
            'runs' => PaymentRun::withCount('payments')->latest('payment_date')->paginate(20),
            'openInvoices' => SupplierInvoice::with('supplier')->whereIn('status', ['posted', 'part_paid'])->orderBy('due_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'run_number' => ['required', 'max:60', Rule::unique('accounting_payment_runs')->where('company_id', $request->user()->company_id)],
            'payment_date' => ['required', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'payment_method' => ['required', 'max:40'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => [new TenantExists('accounting_supplier_invoices')],
        ]);

        DB::transaction(function () use ($data, $request) {
            $invoices = SupplierInvoice::with('supplier')->whereIn('id', $data['invoice_ids'])->get();
            $run = PaymentRun::create([
                'run_number' => $data['run_number'],
                'payment_date' => $data['payment_date'],
                'currency' => strtoupper($data['currency']),
                'payment_method' => $data['payment_method'],
                'total_amount' => $invoices->sum(fn ($invoice) => $invoice->balance),
            ]);

            foreach ($invoices as $invoice) {
                SupplierPayment::create([
                    'company_id' => $request->user()->company_id,
                    'payment_run_id' => $run->id,
                    'supplier_id' => $invoice->supplier_id,
                    'supplier_invoice_id' => $invoice->id,
                    'payment_number' => $run->run_number.'-'.$invoice->id,
                    'payment_date' => $run->payment_date,
                    'currency' => $run->currency,
                    'amount' => $invoice->balance,
                    'payment_method' => $run->payment_method,
                ]);
            }
        });

        return back()->with('success', 'Payment run created.');
    }

    public function show(PaymentRun $paymentRun): View
    {
        $paymentRun->load(['payments.supplier', 'payments.invoice']);
        return view('accounting.ap.payment_runs.show', compact('paymentRun'));
    }

    public function post(PaymentRun $paymentRun, CommercialPostingService $postingService): RedirectResponse
    {
        DB::transaction(function () use ($paymentRun, $postingService) {
            $run = PaymentRun::lockForUpdate()->findOrFail($paymentRun->id);
            if ($run->status === 'posted') {
                return;
            }

            $run->load('payments.invoice');
            foreach ($run->payments as $payment) {
                $postingService->postSupplierPayment($payment, $payment->invoice);
            }
            $run->update(['status' => 'posted']);
        });

        return back()->with('success', 'Payment run posted.');
    }
}

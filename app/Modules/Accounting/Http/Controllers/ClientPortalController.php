<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerReceipt;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Services\PortalCredentialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function __construct(private readonly PortalCredentialService $credentials)
    {
    }

    public function loginForm(): View
    {
        return view('portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $customer = $this->credentials->findCustomer($credentials['email'], $credentials['password']);

        if (!$customer) {
            return back()->withErrors(['email' => 'The portal login details are incorrect.'])->onlyInput('email');
        }

        $this->credentials->startSession($request, $customer);

        if ($request->session()->get('client_must_change_password')) {
            return redirect()->route('portal.password.change');
        }

        return redirect()->route('portal.dashboard');
    }

    public function changePasswordForm(Request $request): View|RedirectResponse
    {
        if (!$this->customer($request)) {
            return redirect()->route('portal.login');
        }

        return view('portal.change-password');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);

        if (!$customer) {
            return redirect()->route('portal.login');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if ($customer->portal_password && Hash::check($data['password'], $customer->portal_password)) {
            return back()->withErrors(['password' => 'Choose a password different from your temporary password.']);
        }

        $customer->forceFill([
            'portal_password' => Hash::make($data['password']),
            'portal_password_changed_at' => now(),
        ])->save();

        $request->session()->forget('client_must_change_password');

        return redirect()->route('portal.dashboard')->with('success', 'Your portal password has been updated.');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $customer = $this->customer($request);

        if (!$customer) {
            return redirect()->route('portal.login');
        }

        if ($request->session()->get('client_must_change_password') || !$customer->portal_password_changed_at) {
            return redirect()->route('portal.password.change');
        }

        $invoices = SalesInvoice::withoutGlobalScopes()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->latest('invoice_date')
            ->paginate(20);

        $payments = CustomerReceipt::withoutGlobalScopes()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->latest('receipt_date')
            ->take(10)
            ->get();

        return view('portal.dashboard', compact('customer', 'invoices', 'payments'));
    }

    public function payment(Request $request, int $invoice): RedirectResponse
    {
        $customer = $this->customer($request);

        if (!$customer) {
            return redirect()->route('portal.login');
        }

        if ($request->session()->get('client_must_change_password') || !$customer->portal_password_changed_at) {
            return redirect()->route('portal.password.change');
        }

        $invoice = SalesInvoice::withoutGlobalScopes()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($invoice);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$invoice->balance],
            'payment_method' => ['required', 'max:40'],
            'memo' => ['nullable', 'max:1000'],
        ]);

        CustomerReceipt::withoutGlobalScopes()->create([
            'company_id' => $invoice->company_id,
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'receipt_number' => 'PORTAL-'.now()->format('YmdHis').'-'.$invoice->id,
            'receipt_date' => now()->toDateString(),
            'currency' => $invoice->currency,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'memo' => trim(($data['memo'] ?? '')."\nSubmitted through client portal by {$customer->email}."),
            'status' => 'draft',
        ]);

        return back()->with('success', 'Payment update submitted for finance review.');
    }

    public function invoicePdf(Request $request, int $invoice): Response|RedirectResponse
    {
        $customer = $this->customer($request);

        if (!$customer) {
            return redirect()->route('portal.login');
        }

        if ($request->session()->get('client_must_change_password') || !$customer->portal_password_changed_at) {
            return redirect()->route('portal.password.change');
        }

        $invoice = SalesInvoice::withoutGlobalScopes()
            ->with(['customer', 'lines'])
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($invoice);

        $pdf = Pdf::loadView('portal.invoice-pdf', [
            'customer' => $customer,
            'invoice' => $invoice,
            'company' => $customer->company,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['client_customer_id', 'client_company_id', 'client_must_change_password']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('success', 'You have been logged out.');
    }

    public function show(string $token): RedirectResponse
    {
        return redirect()->route('portal.login')->with('success', 'Please log in to access the client portal.');
    }

    private function customer(Request $request): ?Customer
    {
        $id = $request->session()->get('client_customer_id');
        $companyId = $request->session()->get('client_company_id');

        if (!$id || !$companyId) {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('status', true)
            ->whereNotNull('portal_password')
            ->first();
    }
}

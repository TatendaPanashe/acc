<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Services\PortalCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('accounting.ar.customers.index', [
            'customers' => Customer::orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validated($request));
        return back()->with('success', 'Customer created.');
    }

    public function edit(Customer $customer): View
    {
        return view('accounting.ar.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));
        return redirect()->route('accounting.ar.customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->update(['status' => false]);
        return back()->with('success', 'Customer deactivated.');
    }

    public function issuePortalPassword(Customer $customer, PortalCredentialService $credentials): RedirectResponse
    {
        if (!$customer->email) {
            return back()->with('error', 'Add an email address to this customer before issuing portal access.');
        }

        $password = $credentials->issueTemporaryPassword($customer);

        return back()
            ->with('success', 'Temporary portal password issued for '.$customer->email.'. The customer must change it on first login.')
            ->with('portal_temporary_password', $password);
    }

    public function revokePortalPassword(Customer $customer, PortalCredentialService $credentials): RedirectResponse
    {
        $credentials->revoke($customer);

        return back()->with('success', 'Portal access revoked for '.$customer->name.'.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'customer_number' => ['required', 'max:40', Rule::unique('accounting_customers')->where('company_id', $request->user()->company_id)->ignore($customer)],
            'name' => ['required', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'max:80'],
            'billing_address' => ['nullable', 'max:1000'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'size:3'],
            'status' => ['nullable', 'boolean'],
        ]);
    }
}

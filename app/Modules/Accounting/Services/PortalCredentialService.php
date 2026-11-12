<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Single source of truth for client-portal credentials.
 *
 * Customers have no portal access until staff issue a one-time password,
 * which the customer must replace on first login.
 */
class PortalCredentialService
{
    public function findCustomer(string $email, string $password): ?Customer
    {
        $email = trim($email);

        if ($email === '' || $password === '') {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->where('email', $email)
            ->where('status', true)
            ->whereNotNull('portal_password')
            ->get()
            ->first(fn (Customer $customer) => Hash::check($password, $customer->portal_password));
    }

    /**
     * Generate a random temporary password, store its hash and force a change on next login.
     * Returns the plain-text password so it can be handed to the customer exactly once.
     */
    public function issueTemporaryPassword(Customer $customer): string
    {
        $password = Str::password(14, symbols: false);

        $customer->forceFill([
            'portal_password' => Hash::make($password),
            'portal_password_changed_at' => null,
        ])->save();

        return $password;
    }

    public function revoke(Customer $customer): void
    {
        $customer->forceFill([
            'portal_password' => null,
            'portal_password_changed_at' => null,
        ])->save();
    }

    public function startSession(Request $request, Customer $customer): void
    {
        $customer->forceFill(['portal_last_login_at' => now()])->save();

        $request->session()->regenerate();
        $request->session()->put('client_customer_id', $customer->id);
        $request->session()->put('client_company_id', $customer->company_id);

        if (!$customer->portal_password_changed_at) {
            $request->session()->put('client_must_change_password', true);
        }
    }
}

<?php

namespace App\Modules\Payroll\Services;

use App\Models\Company;
use App\Modules\Payroll\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Employee portal credentials and session. Employees have no portal access until payroll staff issue a
 * one-time password, which the employee must replace at first sign-in. Sign-in is by work email.
 */
class EmployeePortalService
{
    public function find(string $email, string $password): ?Employee
    {
        $email = trim($email);
        if ($email === '' || $password === '') {
            return null;
        }

        return Employee::withoutGlobalScopes()
            ->where('email', $email)->where('status', 'active')->whereNotNull('portal_password')
            ->get()
            ->first(fn (Employee $e) => Hash::check($password, $e->portal_password) && Company::whereKey($e->company_id)->where('status', true)->exists());
    }

    public function issueTemporaryPassword(Employee $employee): string
    {
        $password = Str::password(12, symbols: false);
        $employee->forceFill(['portal_password' => Hash::make($password), 'portal_password_changed_at' => null])->save();

        return $password;
    }

    public function revoke(Employee $employee): void
    {
        $employee->forceFill(['portal_password' => null, 'portal_password_changed_at' => null])->save();
    }

    public function startSession(Request $request, Employee $employee): void
    {
        $employee->forceFill(['portal_last_login_at' => now()])->saveQuietly();
        $request->session()->regenerate();
        $request->session()->put('staff_employee_id', $employee->id);
        $request->session()->put('staff_company_id', $employee->company_id);
        if (!$employee->portal_password_changed_at) {
            $request->session()->put('staff_must_change_password', true);
        }
    }

    /** The signed-in employee for this request, or null (also checks the account is still usable). */
    public function current(Request $request): ?Employee
    {
        $id = $request->session()->get('staff_employee_id');
        $companyId = $request->session()->get('staff_company_id');
        if (!$id || !$companyId) {
            return null;
        }

        $employee = Employee::withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'active')->whereNotNull('portal_password')->find($id);

        return $employee && Company::whereKey($companyId)->where('status', true)->exists() ? $employee : null;
    }

    public function endSession(Request $request): void
    {
        $request->session()->forget(['staff_employee_id', 'staff_company_id', 'staff_must_change_password']);
        $request->session()->regenerate();
    }
}

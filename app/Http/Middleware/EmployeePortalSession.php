<?php

namespace App\Http\Middleware;

use App\Modules\Payroll\Services\EmployeePortalService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee portal pages: requires a signed-in, active employee, scopes every company-owned query to the
 * employee's company for this request, and forces a new password after a one-time password.
 */
class EmployeePortalSession
{
    public function __construct(private readonly EmployeePortalService $portal)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $employee = $this->portal->current($request);
        if (!$employee) {
            $this->portal->endSession($request);

            return redirect()->route('staff.login')->with('error', 'Please sign in.');
        }

        app()->instance('tenant.portal_company_id', (int) $employee->company_id);
        $request->attributes->set('employee', $employee);
        View::share('portalEmployee', $employee);

        try {
            if ($request->session()->get('staff_must_change_password') && !$request->routeIs('staff.password.*')) {
                return redirect()->route('staff.password.edit');
            }

            return $next($request);
        } finally {
            // Never let the portal company outlive this request (long-running workers reuse the container).
            app()->forgetInstance('tenant.portal_company_id');
        }
    }
}

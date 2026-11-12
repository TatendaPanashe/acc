<?php

namespace App\Http\Middleware;

use App\Services\CompanySetup\CompanySetupProgress;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the company admin finishes the setup wizard, admins are sent to the next
 * setup step and everyone else sees a "workspace not ready" page.
 */
class EnsureCompanySetupIsComplete
{
    public function __construct(private readonly CompanySetupProgress $progress)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->is_superuser) {
            return $next($request);
        }

        $company = $user->company;

        // A suspended business loses access immediately, including sessions already signed in.
        if ($company && !$company->status) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Your company account is inactive or suspended. Contact the system administrator.',
            ]);
        }

        if ($company && $company->onboarding_completed_at) {
            return $next($request);
        }

        if (!$company || !$user->can('company.manage')) {
            return redirect()->route('company.pending');
        }

        return redirect()->route($this->progress->nextStepRoute($company) ?? 'company.setup.edit');
    }
}

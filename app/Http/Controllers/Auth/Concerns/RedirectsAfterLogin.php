<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Where a user goes once signed in (password or Google): platform staff to the platform,
 * suspended workspaces back out, unfinished workspaces to setup, everyone else to the dashboard.
 */
trait RedirectsAfterLogin
{
    protected function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        if ($user->is_superuser) {
            return redirect()->intended(route('system.platform.dashboard'));
        }

        if (!$user->company || !$user->company->status) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Your company account is inactive or suspended. Contact the system administrator.',
            ]);
        }

        if (!$user->company->onboarding_completed_at) {
            // Admins continue the setup wizard; other roles see "workspace not ready".
            return redirect()->route('dashboard');
        }

        return redirect()->intended('/dashboard');
    }
}

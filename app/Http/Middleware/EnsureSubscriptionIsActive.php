<?php

namespace App\Http\Middleware;

use App\Services\Billing\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Locks the workspace when the trial has ended or the subscription is unpaid/cancelled. Billing stays reachable. */
class EnsureSubscriptionIsActive
{
    public function __construct(private readonly SubscriptionAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $user->is_superuser || !$user->company) {
            return $next($request);
        }

        $access = $this->access->check($user->company->subscription()->first());
        if ($access['state'] === 'open') {
            return $next($request);
        }

        return $user->can('company.manage')
            ? redirect()->route('billing.index')->with('warning', $access['message'])
            : redirect()->route('billing.locked');
    }
}

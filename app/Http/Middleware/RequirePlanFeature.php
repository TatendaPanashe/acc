<?php

namespace App\Http\Middleware;

use App\Services\Billing\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Screens that belong to a higher plan (config billing.route_features) show an upgrade prompt instead. */
class RequirePlanFeature
{
    public function __construct(private readonly Entitlements $entitlements)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = (string) $request->route()?->getName();
        if (!$user || $user->is_superuser || !$user->company) {
            return $next($request);
        }

        foreach (config('billing.route_features') as $pattern => $feature) {
            if (Str::is($pattern, $route)) {
                if ($this->entitlements->has($user->company, $feature)) {
                    break;
                }
                $message = $this->entitlements->upgradeMessage($feature);

                return $user->can('company.manage')
                    ? redirect()->route('billing.index')->with('warning', $message)
                    : redirect()->back(fallback: route('dashboard'))->with('error', $message.' Ask your Company Admin.');
            }
        }

        return $next($request);
    }
}

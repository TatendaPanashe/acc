<?php

namespace App\Http\Middleware;

use App\Services\Authorization\RoutePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies any authenticated request whose route the user's role is not allowed to use.
 * Routes without a matching rule in config/permissions.php are denied by default.
 */
class EnforceRoutePermission
{
    public function __construct(private readonly RoutePermissions $permissions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $module = $route?->parameter('module');

        $allowed = $this->permissions->allows(
            $request->user(),
            (string) $route?->getName(),
            $request->method(),
            is_string($module) ? $module : null,
        );

        abort_unless($allowed, 403, 'Your role does not have access to this action.');

        return $next($request);
    }
}

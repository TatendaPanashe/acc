<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'billing/webhooks/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\Admin::class,
            'company.setup' => \App\Http\Middleware\EnsureCompanySetupIsComplete::class,
            'superuser' => \App\Http\Middleware\Superuser::class,
            'permission' => \App\Http\Middleware\EnforceRoutePermission::class,
            'subscription' => \App\Http\Middleware\EnsureSubscriptionIsActive::class,
            'plan.feature' => \App\Http\Middleware\RequirePlanFeature::class,
            'approval.rules' => \App\Http\Middleware\EnforceApprovalRules::class,
            'staff.portal' => \App\Http\Middleware\EmployeePortalSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

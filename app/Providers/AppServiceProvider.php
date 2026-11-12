<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Authorization\RoutePermissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissions();
        $this->registerAuditTrail();

        RateLimiter::for('portal-login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('portal-login:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('portal-login-ip:'.$request->ip()),
            ];
        });
    }

    private function registerPermissions(): void
    {
        Gate::before(fn (User $user) => $user->is_superuser ? true : null);

        foreach (array_keys(config('permissions.abilities', [])) as $ability) {
            Gate::define($ability, fn (User $user) => RoutePermissions::roleHas($user->role, $ability));
        }

        // @canroute('accounting.gl.journals.post', 'POST') ... @else ... @endcanroute
        Blade::if('canroute', fn (string $name, string $method = 'GET', ?string $module = null) => app(RoutePermissions::class)
            ->allows(auth()->user(), $name, $method, $module));
    }

    /** Sign-ins and team changes go to the company's audit trail (accounting records audit themselves). */
    private function registerAuditTrail(): void
    {
        $audit = fn () => app(\App\Modules\Accounting\Services\AuditTrailService::class);

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) use ($audit) {
            if ($event->user->company_id) {
                $audit()->record('auth.login', null, [], ['user' => $event->user->username], null, $event->user->company_id);
            }
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) use ($audit) {
            if ($event->user?->company_id) {
                $audit()->record('auth.failed', null, [], ['user' => $event->user->username], null, $event->user->company_id);
            }
        });

        User::created(function (User $user) use ($audit) {
            if ($user->company_id) {
                $audit()->record('user.created', $user, [], $user->only(['name', 'username', 'email', 'role']), null, $user->company_id);
            }
        });
        User::updated(function (User $user) use ($audit) {
            $changes = array_intersect_key($user->getChanges(), array_flip(['name', 'username', 'email', 'role', 'password']));
            if ($user->company_id && $changes) {
                if (isset($changes['password'])) {
                    $changes['password'] = 'changed';
                }
                $old = array_intersect_key($user->getOriginal(), $changes);
                unset($old['password']);
                $audit()->record('user.updated', $user, $old, $changes, null, $user->company_id);
            }
        });
        User::deleted(function (User $user) use ($audit) {
            if ($user->company_id) {
                $audit()->record('user.deleted', $user, $user->only(['name', 'username', 'email', 'role']), [], null, $user->company_id);
            }
        });
    }
}

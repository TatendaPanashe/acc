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
}

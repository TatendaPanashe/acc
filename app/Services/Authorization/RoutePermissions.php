<?php

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Resolves which ability a named route requires, using config/permissions.php.
 */
class RoutePermissions
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function abilityFor(string $routeName, string $method = 'GET', ?string $module = null): ?string
    {
        $isWrite = !in_array(strtoupper($method), self::READ_METHODS, true)
            || Str::endsWith($routeName, ['.create', '.edit']);

        foreach (config('permissions.routes', []) as $pattern => $rule) {
            if (!Str::is($pattern, $routeName)) {
                continue;
            }

            if (is_array($rule) && array_key_exists('module', $rule)) {
                $rule = config('permissions.modules')[$rule['module'] ?? $module] ?? null;

                if (!$rule) {
                    return null;
                }
            }

            if (is_string($rule)) {
                return $rule;
            }

            return $rule[$isWrite ? 'write' : 'read'] ?? null;
        }

        return null;
    }

    public function allows(?User $user, string $routeName, string $method = 'GET', ?string $module = null): bool
    {
        if (!$user) {
            return false;
        }

        $ability = $this->abilityFor($routeName, $method, $module);

        return $ability !== null && $user->can($ability);
    }

    public static function roleHas(?string $role, string $ability): bool
    {
        // Ability names contain dots, so read the array directly rather than via config dot-notation.
        return in_array($role, config('permissions.abilities')[$ability] ?? [], true);
    }
}

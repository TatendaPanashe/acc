<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\Platform\Plan;
use App\Models\User;
use App\Notifications\Account\NewSignupAlert;
use App\Notifications\Account\WelcomeToWorkspace;
use App\Services\Notifications\Notifier;
use App\Services\Platform\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a new company workspace with its first admin user. Shared by the sign-up form and
 * "Continue with Google", so both start the same trial and send the same emails.
 */
class WorkspaceRegistrar
{
    /**
     * @param  array{name: string, email: string, password: string, plan?: ?string, google_id?: ?string, email_verified?: bool}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => Str::limit('New workspace for '.$data['name'].' '.now()->format('YmdHis'), 255, ''),
                'status' => true,
            ]);

            // The trial starts on the default plan when the company is created; switch to the chosen one.
            $chosen = !empty($data['plan']) ? $this->signupPlan($data['plan']) : null;
            if ($chosen && $company->subscription && $company->subscription->platform_plan_id !== $chosen->id) {
                app(SubscriptionService::class)->changePlan($company->subscription, $chosen, 'monthly', 'Chosen at sign-up');
            }

            $user = User::create([
                'name' => $data['name'],
                'username' => $this->uniqueUsername($data['email']),
                'company_id' => $company->id,
                'email' => $data['email'],
                'google_id' => $data['google_id'] ?? null,
                'role' => 'admin',
                'password' => Hash::make($data['password']),
            ]);

            if (!empty($data['email_verified'])) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        });
    }

    /** Welcome email to the new admin, sent once their email address is confirmed. */
    public function welcome(User $user): void
    {
        $sub = $user->company?->subscription;

        app(Notifier::class)->send('account.welcome', $user,
            new WelcomeToWorkspace($user, $sub?->plan?->name, $sub?->trial_ends_at?->format('d M Y')), $user->company_id);
    }

    /** Sign-up alert to the platform inbox, sent as soon as the account exists. */
    public function alertPlatform(User $user): void
    {
        if ($platform = config('notifications.platform_email')) {
            app(Notifier::class)->send('account.welcome', $platform, new NewSignupAlert($user, $user->company?->subscription?->plan?->name), null);
        }
    }

    /** An active, public plan a new sign-up may choose, or null. */
    public function signupPlan(?string $code): ?Plan
    {
        return $code ? Plan::where('code', $code)->where('is_active', true)->where('is_custom', false)->first() : null;
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_') ?: 'user';
        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$suffix++;
        }

        return $username;
    }
}

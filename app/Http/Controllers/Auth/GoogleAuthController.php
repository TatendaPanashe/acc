<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\RedirectsAfterLogin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\WorkspaceRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" for sign-in and sign-up.
 *
 * - A Google account already linked to a user signs that user in.
 * - Otherwise a user with the same, Google-verified email is linked and signed in.
 * - Otherwise, when started from the sign-up page, a new workspace is created exactly as the
 *   sign-up form does. From the sign-in page we never create one: customers sign in there too,
 *   and a stray click must not open a workspace for them.
 */
class GoogleAuthController extends Controller
{
    use RedirectsAfterLogin;

    public function __construct()
    {
        $this->middleware('guest');
    }

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (!self::enabled()) {
            $page = $request->query('intent') === 'signup' ? 'register' : 'login';

            return redirect()->route($page)->withErrors([
                $page === 'register' ? 'email' : 'login' => 'Google sign-in is not set up yet. Please use your email and password for now.',
            ]);
        }

        // Remember where the user started, and the plan picked on the sign-up page, for the callback.
        $plan = app(WorkspaceRegistrar::class)->signupPlan($request->query('plan'));
        $request->session()->put('google_auth', [
            'signup' => $request->query('intent') === 'signup',
            'plan' => $plan?->code,
        ]);

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, WorkspaceRegistrar $registrar): RedirectResponse
    {
        if (!self::enabled()) {
            return $this->fail('Google sign-in is not set up yet. Please use your email and password for now.');
        }

        $intent = (array) $request->session()->pull('google_auth', []);

        if ($request->filled('error')) {
            // The user closed or declined Google's consent screen.
            return $this->fail('Google sign-in was cancelled.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed', ['error' => $e->getMessage()]);

            return $this->fail('We could not sign you in with Google. Please try again.');
        }

        $email = Str::lower(trim((string) $google->getEmail()));
        $verified = filter_var($google->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || !$verified) {
            return $this->fail('Your Google account email is not verified. Verify it with Google, or sign in with your password.');
        }

        $user = User::where('google_id', $google->getId())->first();

        if (!$user && ($user = User::where('email', $email)->first())) {
            if ($user->google_id && $user->google_id !== $google->getId()) {
                return $this->fail('This email is linked to a different Google account. Sign in with your password instead.');
            }
            // Google has confirmed this person owns the email, so link the account.
            $user->forceFill(['google_id' => $google->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        }

        $isNew = false;
        if (!$user && empty($intent['signup'])) {
            return redirect()->route('register')->withInput(['email' => $email])->withErrors([
                'email' => "There is no acc263 account for {$email} yet. Create one below, or use Continue with Google here to start your free trial.",
            ]);
        }

        if (!$user) {
            $user = $registrar->register([
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32), // never shown; they can set one with "Forgot password".
                'plan' => $intent['plan'] ?? null,
                'google_id' => $google->getId(),
                'email_verified' => true,
            ]);
            $isNew = true;
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($isNew) {
            // Google has already confirmed the email, so no verification step: welcome straight away.
            $registrar->alertPlatform($user);
            $registrar->welcome($user);

            return redirect('/company/setup');
        }

        return $this->redirectAfterLogin($request, $user);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}

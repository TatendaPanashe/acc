<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\PortalCredentialService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function username()
    {
        return 'login';
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    protected function attemptLogin(Request $request)
    {
        $login = trim((string) $request->input('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        return Auth::guard()->attempt([
            $field => $login,
            'password' => $request->input('password'),
        ], $request->boolean('remember'));
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        if ($this->attemptClientPortalLogin($request)) {
            return redirect()->route(
                $request->session()->get('client_must_change_password')
                    ? 'portal.password.change'
                    : 'portal.dashboard'
            );
        }

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    protected function authenticated(Request $request, $user)
    {
        if ($user->is_superuser) {
            return redirect()->intended(route('system.platform.dashboard'));
        }

        if (!$user->company || !$user->company->status) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Your company account is inactive or suspended. Contact the system administrator.',
            ]);
        }

        if (!$user->is_superuser && !$user->company->onboarding_completed_at) {
            // Admins continue the setup wizard; other roles see "workspace not ready".
            return redirect()->route('dashboard');
        }

        return redirect()->intended($this->redirectPath());
    }

    private function attemptClientPortalLogin(Request $request): bool
    {
        $email = trim((string) $request->input('login'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $credentials = app(PortalCredentialService::class);
        $customer = $credentials->findCustomer($email, (string) $request->input('password'));

        if (!$customer) {
            return false;
        }

        $this->clearLoginAttempts($request);
        $credentials->startSession($request, $customer);

        return true;
    }
}

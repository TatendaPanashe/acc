<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\WorkspaceRegistrar;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers {
        register as protected registerUser;
    }

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/company/setup';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /** Tidy the input (trim, single spaces, lower-case email) before it is validated and saved. */
    public function register(Request $request)
    {
        $request->merge([
            'name' => Str::squish((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        return $this->registerUser($request);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            // Letters (any language), spaces, apostrophes, hyphens and full stops; at least two letters.
            'name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\\p{L}][\\p{L}\\p{M}' .\\-]*$/u"],
            'email' => ['required', 'string', 'max:255', 'email:rfc,strict', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers(), Rule::notIn([$data['email'] ?? ''])],
            'plan' => ['nullable', Rule::exists('platform_plans', 'code')->where('is_active', 1)->where('is_custom', 0)],
        ], [
            'name.regex' => 'Use letters only for your name (spaces, hyphens and apostrophes are fine).',
            'email.email' => 'Enter a valid email address, like you@company.co.zw.',
            'email.unique' => 'An account with this email already exists. Sign in instead, or reset your password.',
            'password.confirmed' => 'The two passwords do not match.',
            'password.not_in' => 'Your password cannot be the same as your email address.',
            'password.max' => 'Use a password of at most 72 characters.',
            'plan.exists' => 'Choose one of the plans in the list.',
        ], [
            'name' => 'full name',
            'email' => 'email address',
        ]);
    }

    /**
     * Create the workspace and its first admin user after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return app(WorkspaceRegistrar::class)->register($data);
    }

    /**
     * Alert the platform inbox. The Registered event has already emailed the verification link;
     * the welcome email follows once the address is confirmed (VerificationController::verified).
     */
    protected function registered(\Illuminate\Http\Request $request, $user)
    {
        app(WorkspaceRegistrar::class)->alertPlatform($user);

        return redirect()->route('verification.notice');
    }
}

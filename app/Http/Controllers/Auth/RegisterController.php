<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

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

    use RegistersUsers;

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

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan' => ['nullable', \Illuminate\Validation\Rule::exists('platform_plans', 'code')->where('is_active', 1)->where('is_custom', 0)],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => Str::limit('New workspace for '.$data['name'].' '.now()->format('YmdHis'), 255, ''),
                'status' => true,
            ]);

            // The trial starts on the default plan when the company is created; switch to the chosen one.
            $chosen = !empty($data['plan']) ? \App\Models\Platform\Plan::where('code', $data['plan'])->first() : null;
            if ($chosen && $company->subscription && $company->subscription->platform_plan_id !== $chosen->id) {
                app(\App\Services\Platform\SubscriptionService::class)->changePlan($company->subscription, $chosen, 'monthly', 'Chosen at sign-up');
            }

            return User::create([
                'name' => $data['name'],
                'username' => $this->uniqueUsername($data['email']),
                'company_id' => $company->id,
                'email' => $data['email'],
                'role' => 'admin',
                'password' => Hash::make($data['password']),
            ]);
        });
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

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\WorkspaceRegistrar;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */

    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
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
        $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    /** Email confirmed: now send the welcome email (sign-up only sent the verification link). */
    protected function verified(Request $request)
    {
        if ($request->user()->role === 'admin' && !$request->user()->company?->onboarding_completed_at) {
            app(WorkspaceRegistrar::class)->welcome($request->user());
        }

        return redirect($this->redirectPath())->with('success', 'Thanks, your email is verified. Let’s set up your workspace.');
    }
}

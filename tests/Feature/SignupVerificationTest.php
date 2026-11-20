<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Notifications\Account\VerifyEmailAddress;
use App\Notifications\Account\WelcomeToWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SignupVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $usersStartVerified = false;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function signUp(array $overrides = [])
    {
        return $this->post('/register', $overrides + [
            'name' => 'Nyasha Moyo', 'email' => 'nyasha@example.com',
            'password' => 'Harare2026', 'password_confirmation' => 'Harare2026',
        ]);
    }

    private function verifyUrl(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($email ?? $user->email)]);
    }

    // ---- validation --------------------------------------------------------------

    public function test_sign_up_rejects_weak_or_malformed_input(): void
    {
        $this->signUp(['name' => 'N', 'email' => 'not-an-email', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $this->signUp(['name' => 'Nyasha 123'])->assertSessionHasErrors(['name' => 'Use letters only for your name (spaces, hyphens and apostrophes are fine).']);
        $this->signUp(['password' => 'harare2026', 'password_confirmation' => 'harare2026'])->assertSessionHasErrors('password'); // no capital
        $this->signUp(['password' => 'HarareCity', 'password_confirmation' => 'HarareCity'])->assertSessionHasErrors('password'); // no number
        $this->signUp(['password_confirmation' => 'Harare2025'])->assertSessionHasErrors(['password' => 'The two passwords do not match.']);
        $this->signUp(['plan' => 'no-such-plan'])->assertSessionHasErrors('plan');

        $this->assertSame(0, User::count());
    }

    public function test_email_is_normalised_and_must_be_unique_in_any_case(): void
    {
        $this->signUp(['name' => "  Nyasha   O'Brien-Moyo ", 'email' => '  Nyasha@Example.COM '])->assertSessionHasNoErrors();
        $user = User::firstOrFail();
        $this->assertSame(['nyasha@example.com', "Nyasha O'Brien-Moyo"], [$user->email, $user->name]);

        auth()->logout();
        $this->signUp(['email' => 'NYASHA@example.com'])
            ->assertSessionHasErrors(['email' => 'An account with this email already exists. Sign in instead, or reset your password.']);
        $this->assertSame(1, User::count());
    }

    public function test_sign_up_form_has_browser_side_checks(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('id="register-form"', false)
            ->assertSee('minlength="8"', false)
            ->assertSee('At least one number');
    }

    // ---- email confirmation ----------------------------------------------------------

    public function test_new_account_must_confirm_email_before_using_the_app(): void
    {
        $this->signUp(['plan' => 'essential'])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'nyasha@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailAddress::class);
        Notification::assertNotSentTo($user, WelcomeToWorkspace::class);

        $this->get(route('verification.notice'))->assertOk()->assertSee('nyasha@example.com')->assertSee('Send the link again');
        foreach (['/dashboard', '/company/setup', '/billing', '/help'] as $page) {
            $this->get($page)->assertRedirect(route('verification.notice'));
        }
    }

    public function test_signing_in_before_confirming_still_lands_on_the_confirm_page(): void
    {
        $this->signUp();
        auth()->logout();

        $this->post('/login', ['login' => 'nyasha@example.com', 'password' => 'Harare2026']);
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_confirmation_link_opens_the_workspace_and_sends_the_welcome(): void
    {
        $this->signUp();
        $user = User::where('email', 'nyasha@example.com')->firstOrFail();

        $this->get($this->verifyUrl($user))->assertRedirect('/dashboard');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, WelcomeToWorkspace::class);
        $this->get('/dashboard')->assertRedirect(route('company.setup.edit'));
        $this->get(route('company.setup.edit'))->assertOk();
    }

    public function test_tampered_or_expired_links_do_not_confirm(): void
    {
        $this->signUp();
        $user = User::where('email', 'nyasha@example.com')->firstOrFail();

        $this->get($this->verifyUrl($user, 'someone-else@example.com'))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();
        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden(); // unsigned

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_link_can_be_resent(): void
    {
        $this->signUp();
        $user = User::where('email', 'nyasha@example.com')->firstOrFail();

        $this->post(route('verification.resend'))->assertSessionHas('resent', true);
        Notification::assertSentToTimes($user, VerifyEmailAddress::class, 2);
    }

    public function test_verification_email_contains_the_signed_link(): void
    {
        $this->signUp();
        $user = User::where('email', 'nyasha@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailAddress::class, function ($notification) use ($user) {
            $html = $notification->toMail($user)->render();

            return str_contains($html, 'Confirm my email') && str_contains($html, '/email/verify/'.$user->id.'/');
        });
    }

    public function test_mail_server_failure_does_not_break_sign_up_and_is_reported(): void
    {
        $failing = new class extends \Illuminate\Support\Testing\Fakes\NotificationFake {
            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new \RuntimeException('Connection could not be established with host "smtp.example.test:465"');
            }
        };
        Notification::swap($failing);
        \Illuminate\Support\Facades\Log::spy();

        $this->signUp()->assertRedirect(route('verification.notice'))->assertSessionHas('verification_failed', true);

        $this->assertSame(1, User::count());
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'Verification email to nyasha@example.com failed'));
        $this->get(route('verification.notice'))->assertOk();
    }

    public function test_verify_page_explains_a_failed_send(): void
    {
        $this->signUp();

        $this->withSession(['verification_failed' => true])->get(route('verification.notice'))
            ->assertOk()->assertSee('We could not send the confirmation email just now');
    }

    public function test_team_members_added_by_an_admin_do_not_need_to_confirm(): void
    {
        $company = Company::create(['name' => 'Acme', 'status' => true, 'onboarding_completed_at' => now()]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin1', 'email' => 'admin@acme.test', 'password' => 'secret-pass', 'company_id' => $company->id, 'role' => 'admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->post(route('company.team.store'), [
            'name' => 'Clerk', 'username' => 'clerk', 'email' => 'clerk@acme.test', 'role' => 'accountant',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('company.team.index'));

        $this->assertNotNull(User::where('email', 'clerk@acme.test')->value('email_verified_at'));
    }
}

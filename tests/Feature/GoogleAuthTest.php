<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
    }

    private function googleReturns(string $email, string $id = 'google-123', bool $verified = true, string $name = 'Rudo Moyo'): void
    {
        $user = (new GoogleUser)->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified, 'name' => $name])
            ->map(['id' => $id, 'email' => $email, 'name' => $name]);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    private function startedFrom(string $intent, ?string $plan = null): array
    {
        return ['google_auth' => ['signup' => $intent === 'signup', 'plan' => $plan]];
    }

    private function existingUser(array $attributes = [], bool $onboarded = true): User
    {
        $company = Company::create(['name' => 'Acme '.uniqid(), 'status' => true, 'onboarding_completed_at' => $onboarded ? now() : null]);

        return User::create($attributes + [
            'name' => 'Rudo', 'username' => 'rudo'.uniqid(), 'email' => 'rudo@example.com',
            'password' => 'secret-password', 'company_id' => $company->id, 'role' => 'admin',
        ]);
    }

    public function test_button_is_on_sign_in_and_sign_up_pages(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Continue with Google')->assertSee('intent=signin', false);
        $this->get(route('register'))->assertOk()->assertSee('Continue with Google')->assertSee('intent=signup', false);
    }

    public function test_button_explains_when_google_is_not_set_up(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('login'))->assertOk()->assertSee('Continue with Google');
        $this->get(route('auth.google.redirect', ['intent' => 'signin']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->get(route('auth.google.redirect', ['intent' => 'signup']))
            ->assertRedirect(route('register'))->assertSessionHasErrors('email');
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('login');
    }

    public function test_redirect_sends_the_user_to_google_and_remembers_the_plan(): void
    {
        $response = $this->get(route('auth.google.redirect', ['intent' => 'signup', 'plan' => 'essential']));

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $response->headers->get('Location'));
        $response->assertSessionHas('google_auth', ['signup' => true, 'plan' => 'essential']);
    }

    public function test_sign_up_with_google_creates_a_workspace_on_the_chosen_plan(): void
    {
        $this->googleReturns('Rudo@Example.com');

        $this->withSession($this->startedFrom('signup', 'essential'))
            ->get(route('auth.google.callback'))
            ->assertRedirect('/company/setup');

        $user = User::where('email', 'rudo@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['google-123', 'admin', 'Rudo Moyo'], [$user->google_id, $user->role, $user->name]);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(['trialing', 'essential'], [$user->company->subscription->status, $user->company->subscription->plan->code]);
    }

    public function test_existing_user_is_linked_by_verified_email_and_signed_in(): void
    {
        $user = $this->existingUser();
        $this->googleReturns('rudo@example.com');

        $this->withSession($this->startedFrom('signin'))->get(route('auth.google.callback'))->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_linked_google_account_signs_in_even_after_email_change(): void
    {
        $user = $this->existingUser(['email' => 'new-address@example.com', 'google_id' => 'google-123']);
        $this->googleReturns('rudo@example.com');

        $this->withSession($this->startedFrom('signin'))->get(route('auth.google.callback'))->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_sign_in_never_creates_a_workspace(): void
    {
        $this->googleReturns('stranger@example.com');

        $this->withSession($this->startedFrom('signin'))->get(route('auth.google.callback'))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $this->existingUser();
        $this->googleReturns('rudo@example.com', verified: false);

        $this->withSession($this->startedFrom('signin'))->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_email_linked_to_another_google_account_is_refused(): void
    {
        $this->existingUser(['google_id' => 'google-original']);
        $this->googleReturns('rudo@example.com', id: 'google-other');

        $this->withSession($this->startedFrom('signin'))->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_suspended_workspace_cannot_sign_in_with_google(): void
    {
        $user = $this->existingUser();
        $user->company->update(['status' => false]);
        $this->googleReturns('rudo@example.com');

        $this->withSession($this->startedFrom('signin'))->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_cancelled_consent_returns_to_sign_in(): void
    {
        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}

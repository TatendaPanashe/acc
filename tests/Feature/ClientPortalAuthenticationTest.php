<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Services\PortalCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientPortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_DEFAULT_PASSWORD = 'kodomotech26';

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Acme Ltd',
            'status' => true,
            'onboarding_completed_at' => now(),
        ]);
    }

    private function customer(array $attributes = []): Customer
    {
        $customer = new Customer([
            'customer_number' => 'C'.random_int(1000, 9999),
            'name' => 'Client Co',
            'email' => 'client@example.com',
            'credit_limit' => 0,
            'status' => true,
        ]);
        $customer->company_id = $this->company->id;
        $customer->forceFill($attributes)->save();

        return $customer;
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'Finance Clerk',
            'username' => 'clerk',
            'email' => 'clerk@example.com',
            'password' => Hash::make('secret-password'),
            'company_id' => $this->company->id,
            'role' => 'admin',
        ]);
    }

    public function test_legacy_default_password_no_longer_grants_portal_access(): void
    {
        $this->customer();

        $this->post('/client/login', ['email' => 'client@example.com', 'password' => self::LEGACY_DEFAULT_PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertFalse(session()->has('client_customer_id'));
    }

    public function test_legacy_default_password_no_longer_works_through_staff_login_fallback(): void
    {
        $this->customer();

        $this->post('/login', ['login' => 'client@example.com', 'password' => self::LEGACY_DEFAULT_PASSWORD])
            ->assertSessionHasErrors('login');
        $this->assertFalse(session()->has('client_customer_id'));
    }

    public function test_issued_temporary_password_logs_in_and_forces_change(): void
    {
        $customer = $this->customer();
        $password = app(PortalCredentialService::class)->issueTemporaryPassword($customer);

        $this->post('/client/login', ['email' => 'client@example.com', 'password' => $password])
            ->assertRedirect(route('portal.password.change'));

        $this->get('/client/dashboard')->assertRedirect(route('portal.password.change'));

        $this->post('/client/password/change', ['password' => $password, 'password_confirmation' => $password])
            ->assertSessionHasErrors('password');

        $this->post('/client/password/change', ['password' => 'my-new-pass-123', 'password_confirmation' => 'my-new-pass-123'])
            ->assertRedirect(route('portal.dashboard'));

        $this->assertNotNull($customer->fresh()->portal_password_changed_at);
        $this->get('/client/dashboard')->assertOk();
    }

    public function test_revoking_access_ends_existing_session(): void
    {
        $customer = $this->customer([
            'portal_password' => Hash::make('my-new-pass-123'),
            'portal_password_changed_at' => now(),
        ]);

        $this->post('/client/login', ['email' => 'client@example.com', 'password' => 'my-new-pass-123'])
            ->assertRedirect(route('portal.dashboard'));

        app(PortalCredentialService::class)->revoke($customer);

        $this->get('/client/dashboard')->assertRedirect(route('portal.login'));
    }

    public function test_portal_login_is_rate_limited(): void
    {
        $this->customer();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/client/login', ['email' => 'client@example.com', 'password' => 'wrong'.$i]);
        }

        $this->post('/client/login', ['email' => 'client@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_staff_can_issue_and_revoke_portal_password(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->staff())
            ->post(route('accounting.ar.customers.portal-password.issue', $customer))
            ->assertSessionHas('portal_temporary_password');

        $customer->refresh();
        $this->assertTrue(Hash::check(session('portal_temporary_password'), $customer->portal_password));
        $this->assertNull($customer->portal_password_changed_at);

        $this->delete(route('accounting.ar.customers.portal-password.revoke', $customer));
        $this->assertNull($customer->fresh()->portal_password);
    }

    public function test_staff_cannot_issue_portal_password_for_another_companys_customer(): void
    {
        $otherCompany = Company::create(['name' => 'Other Ltd', 'status' => true, 'onboarding_completed_at' => now()]);
        $foreign = new Customer(['customer_number' => 'X1', 'name' => 'Foreign', 'email' => 'f@example.com', 'credit_limit' => 0, 'status' => true]);
        $foreign->company_id = $otherCompany->id;
        $foreign->saveQuietly();

        $this->actingAs($this->staff())
            ->post(route('accounting.ar.customers.portal-password.issue', $foreign->id))
            ->assertNotFound();

        $this->assertNull(Customer::withoutGlobalScopes()->find($foreign->id)->portal_password);
    }
}

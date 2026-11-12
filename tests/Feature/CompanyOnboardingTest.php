<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\TaxCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithUser(string $role, bool $completed = false): User
    {
        $company = Company::create(['name' => 'Co '.uniqid(), 'status' => true, 'onboarding_completed_at' => $completed ? now() : null]);

        return User::create([
            'name' => 'User', 'username' => 'u'.uniqid(), 'email' => uniqid().'@example.com',
            'password' => 'secret-password', 'company_id' => $company->id, 'role' => $role,
        ]);
    }

    private function scoped(string $model, User $user)
    {
        return $model::withoutGlobalScopes()->where('company_id', $user->company_id);
    }

    public function test_admin_completes_setup_wizard_in_order(): void
    {
        $admin = $this->companyWithUser('admin');
        $this->actingAs($admin);

        $this->get(route('dashboard'))->assertRedirect(route('company.setup.edit'));
        $this->get(route('company.setup.edit'))->assertOk()->assertSee('Save &amp; continue', false);
        $this->get(route('company.setup.ledger'))->assertRedirect(route('company.setup.edit'));

        $this->put(route('company.setup.update'), [
            'name' => 'Acme Ltd', 'vat_number' => 'VAT123', 'country' => 'ZW',
            'bank_accounts' => [['bank_name' => 'CBZ', 'account_name' => 'Acme', 'account_number' => '001', 'currency' => 'USD']],
        ])->assertRedirect(route('company.setup.currencies'));
        $this->get(route('company.setup.currencies'))->assertOk()->assertSee('Zimbabwe Gold');

        $this->post(route('company.setup.currencies.store'), ['base_currency' => 'USD', 'currencies' => ['ZWG']])
            ->assertRedirect(route('company.setup.tax'));
        $this->get(route('company.setup.tax'))->assertOk()->assertSee('VAT-STD');

        $this->post(route('company.setup.tax.store'), ['tin' => '2000123456', 'standard_rate' => 15.5, 'include_exempt' => 1])
            ->assertRedirect(route('company.setup.ledger'));

        $this->get(route('dashboard'))->assertRedirect(route('company.setup.ledger'));
        $this->get(route('company.setup.ledger'))->assertOk()->assertSee('Accounts Receivable');

        $this->post(route('company.setup.ledger.store'), ['start_month' => 7, 'start_year' => 2026])
            ->assertRedirect(route('company.team.index'));

        $company = $admin->company->fresh();
        $this->assertNotNull($company->onboarding_completed_at);
        $this->assertSame('2000123456', $company->tin);
        $this->assertSame('USD', $this->scoped(Currency::class, $admin)->where('is_base', true)->value('code'));
        $this->assertSame(2, $this->scoped(Currency::class, $admin)->count());
        $this->assertSame(3, $this->scoped(TaxCode::class, $admin)->whereNotNull('control_account_id')->count());
        $this->assertTrue($this->scoped(Account::class, $admin)->where('code', '2100')->exists());
        $bankGl = $company->bankAccounts()->first()->gl_account_id;
        $this->assertSame('1010', Account::withoutGlobalScopes()->find($bankGl)->code);
        $this->assertSame($bankGl, \App\Modules\Accounting\Models\AccountMapping::withoutGlobalScopes()->where('company_id', $company->id)->where('key', 'bank')->value('account_id'));

        $year = $this->scoped(FiscalYear::class, $admin)->first();
        $this->assertSame('FY 2026/27', $year->name);
        $this->assertSame('2027-06-30', $year->ends_on->toDateString());
        $this->assertSame(12, $this->scoped(AccountingPeriod::class, $admin)->count());

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('company.team.index'))->assertOk()->assertSee('Add team member');
        $this->get(route('company.team.create'))->assertOk();
        $this->get(route('company.team.edit', $admin))->assertOk();
        $this->get(route('company.setup.currencies'))->assertRedirect(route('dashboard'));
    }

    public function test_non_admin_waits_until_setup_is_complete(): void
    {
        $accountant = $this->companyWithUser('accountant');

        $this->actingAs($accountant)->get(route('dashboard'))->assertRedirect(route('company.pending'));
        $this->actingAs($accountant)->get(route('company.pending'))->assertOk()->assertSee('still being set up');
        $this->actingAs($accountant)->get(route('company.setup.edit'))->assertForbidden();
    }

    public function test_admin_creates_team_members_in_own_company_only(): void
    {
        $admin = $this->companyWithUser('admin', completed: true);

        $this->actingAs($admin)->post(route('company.team.store'), [
            'name' => 'Clerk', 'username' => 'clerk', 'email' => 'clerk@example.com', 'role' => 'accountant',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('company.team.index'));

        $clerk = User::where('username', 'clerk')->firstOrFail();
        $this->assertSame($admin->company_id, $clerk->company_id);
        $this->assertSame('accountant', $clerk->role);
        $this->assertFalse($clerk->is_superuser);

        $outsider = $this->companyWithUser('accountant', completed: true);
        $this->actingAs($admin)->get(route('company.team.edit', $outsider))->assertNotFound();
        $this->actingAs($admin)->delete(route('company.team.destroy', $outsider))->assertNotFound();
    }

    public function test_company_keeps_at_least_one_admin(): void
    {
        $admin = $this->companyWithUser('admin', completed: true);

        $this->actingAs($admin)->put(route('company.team.update', $admin), [
            'name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email, 'role' => 'accountant',
        ])->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }
}

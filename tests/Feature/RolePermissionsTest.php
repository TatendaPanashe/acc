<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerReceipt;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Services\Authorization\RoutePermissions;
use App\Services\CompanySetup\StandardChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createLedgerCompany();
    }

    private function user(string $role, ?Company $company = null): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'username' => $role.$n,
            'email' => $role.$n.'@example.com',
            'password' => 'secret-password',
            'company_id' => ($company ?? $this->company)->id,
            'role' => $role,
        ]);
    }

    private function make(string $class, array $attributes)
    {
        $model = new $class($attributes);
        $model->company_id = $this->company->id;
        $model->save();

        return $model;
    }

    private function account(string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
    }

    private function draftJournal(): JournalEntry
    {
        $journal = $this->make(JournalEntry::class, ['entry_number' => 'JV-'.uniqid(), 'entry_date' => now(), 'currency' => 'USD', 'status' => 'draft']);
        $journal->lines()->create(['company_id' => $this->company->id, 'account_id' => $this->account('6000')->id, 'debit' => 100, 'credit' => 0]);
        $journal->lines()->create(['company_id' => $this->company->id, 'account_id' => $this->account('1000')->id, 'debit' => 0, 'credit' => 100]);

        return $journal;
    }

    private function draftInvoice(): SalesInvoice
    {
        $customer = $this->make(Customer::class, ['customer_number' => 'C'.uniqid(), 'name' => 'Client', 'credit_limit' => 0, 'status' => true]);
        $invoice = $this->make(SalesInvoice::class, [
            'customer_id' => $customer->id, 'invoice_number' => 'INV-'.uniqid(), 'invoice_date' => now(), 'due_date' => now()->addDays(30),
            'currency' => 'USD', 'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'amount_paid' => 0, 'status' => 'draft',
        ]);
        $invoice->lines()->create([
            'company_id' => $this->company->id, 'revenue_account_id' => $this->account('4000')->id,
            'description' => 'Services', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0, 'line_total' => 100,
        ]);

        return $invoice;
    }

    public function test_every_protected_route_has_a_permission_rule(): void
    {
        $permissions = app(RoutePermissions::class);
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (!in_array('permission', $route->gatherMiddleware(), true)) {
                continue;
            }
            $modules = str_contains($route->uri(), '{module}') ? array_keys(config('permissions.modules')) : [null];
            foreach ($modules as $module) {
                if (!$permissions->abilityFor($route->getName(), $route->methods()[0], $module)) {
                    $missing[] = $route->getName().($module ? "[$module]" : '');
                }
            }
        }

        $this->assertSame([], $missing, 'Routes without a rule in config/permissions.php are denied to everyone.');
    }

    public function test_accountant_can_post_sales_invoices(): void
    {
        $invoice = $this->draftInvoice();

        $this->actingAs($this->user('accountant'))
            ->post(route('accounting.ar.invoices.post', $invoice))
            ->assertRedirect();

        $this->assertSame('posted', $invoice->fresh()->status);
    }

    public function test_accountant_can_post_customer_receipts(): void
    {
        $customer = $this->make(Customer::class, ['customer_number' => 'C1', 'name' => 'Client', 'credit_limit' => 0, 'status' => true]);
        $receipt = $this->make(CustomerReceipt::class, [
            'customer_id' => $customer->id, 'receipt_number' => 'RCT-1', 'receipt_date' => now(),
            'currency' => 'USD', 'amount' => 50, 'payment_method' => 'cash', 'status' => 'draft',
        ]);

        $this->actingAs($this->user('accountant'))
            ->post(route('accounting.ar.receipts.post', $receipt))
            ->assertRedirect();

        $this->assertSame('posted', $receipt->fresh()->status);
    }

    public function test_accountant_cannot_post_journals_but_finance_manager_can(): void
    {
        $journal = $this->draftJournal();

        $this->actingAs($this->user('accountant'))
            ->post(route('accounting.gl.journals.post', $journal))
            ->assertForbidden();
        $this->assertSame('draft', $journal->fresh()->status);

        $this->actingAs($this->user('finance_manager'))
            ->post(route('accounting.gl.journals.post', $journal))
            ->assertRedirect();
        $this->assertSame('posted', $journal->fresh()->status);
    }

    public function test_accountant_can_still_draft_journals_for_approval(): void
    {
        $this->actingAs($this->user('accountant'))
            ->get(route('accounting.gl.journals.create'))
            ->assertOk();
    }

    public function test_accountant_is_limited_to_day_to_day_screens(): void
    {
        $accountant = $this->user('accountant');

        foreach (['accounting.gl.trial-balance', 'accounting.gl.balance-sheet', 'accounting.gl.accounts.index',
                  'accounting.settings.index', 'accounting.gl.periods.index', 'company.team.index'] as $route) {
            $this->actingAs($accountant)->get(route($route))->assertForbidden();
        }

        $this->actingAs($accountant)->get(route('accounting.ar.invoices.index'))->assertOk();
        $this->actingAs($accountant)->get(route('accounting.ar.aging.index'))->assertOk();
    }

    public function test_sidebar_only_shows_permitted_links(): void
    {
        $this->actingAs($this->user('accountant'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Invoices')
            ->assertSee('Receipts')
            ->assertDontSee('Trial Balance')
            ->assertDontSee('Chart of Accounts')
            ->assertDontSee('Accounting Settings')
            ->assertDontSee('>Team<', false);

        $this->actingAs($this->user('admin'))
            ->get(route('dashboard'))
            ->assertSee('Trial Balance')
            ->assertSee('Chart of Accounts')
            ->assertSee('Accounting Settings');
    }

    public function test_post_buttons_reflect_role(): void
    {
        $journal = $this->draftJournal();

        $this->actingAs($this->user('accountant'))
            ->get(route('accounting.gl.journals.show', $journal))
            ->assertSee('Awaiting approval')
            ->assertDontSee(route('accounting.gl.journals.post', $journal));

        $this->actingAs($this->user('finance_manager'))
            ->get(route('accounting.gl.journals.show', $journal))
            ->assertSee(route('accounting.gl.journals.post', $journal));
    }

    public function test_auditor_is_read_only(): void
    {
        $auditor = $this->user('auditor');

        $this->actingAs($auditor)->get(route('accounting.gl.trial-balance'))->assertOk();
        $this->actingAs($auditor)->get(route('accounting.ar.invoices.index'))->assertOk()->assertSee('read-only');
        $this->actingAs($auditor)->get(route('accounting.ar.invoices.create'))->assertForbidden();
        $this->actingAs($auditor)->post(route('accounting.ar.customers.store'), ['customer_number' => 'X', 'name' => 'X', 'credit_limit' => 0])->assertForbidden();
        $this->actingAs($auditor)->post(route('accounting.ar.invoices.post', $this->draftInvoice()))->assertForbidden();
    }

    public function test_only_company_admin_manages_settings(): void
    {
        $this->actingAs($this->user('finance_manager'))->get(route('accounting.settings.index'))->assertOk();
        $this->actingAs($this->user('finance_manager'))
            ->post(route('accounting.settings.currencies.store'), ['code' => 'EUR', 'name' => 'Euro'])
            ->assertForbidden();

        $this->actingAs($this->user('admin'))
            ->post(route('accounting.settings.currencies.store'), ['code' => 'EUR', 'name' => 'Euro'])
            ->assertRedirect();
    }
}

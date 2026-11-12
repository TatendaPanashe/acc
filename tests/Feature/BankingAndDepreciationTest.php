<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\BankStatement;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerReceipt;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\SupplierPayment;
use App\Modules\Accounting\Services\CommercialPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class BankingAndDepreciationTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-15 09:00:00');
        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function balance(string $code): float
    {
        $account = $this->ledgerAccount($this->company, $code);

        return round(JournalLine::where('account_id', $account->id)->get()->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2);
    }

    private function bankSetup(): CompanyBankAccount
    {
        $gl = Account::create(['code' => '1010', 'name' => 'Bank - CBZ USD', 'type' => 'asset', 'normal_balance' => 'debit', 'allow_posting' => true, 'status' => true]);
        AccountMapping::create(['key' => 'bank', 'account_id' => $gl->id]);

        return CompanyBankAccount::create([
            'gl_account_id' => $gl->id, 'bank_name' => 'CBZ', 'account_name' => 'Acme', 'account_number' => '001',
            'currency' => 'USD', 'is_primary' => true, 'status' => true,
        ]);
    }

    private function receipt(string $number, string $date, float $amount): void
    {
        $customer = Customer::firstOrCreate(['customer_number' => 'C1'], ['name' => 'Client', 'credit_limit' => 0, 'status' => true]);
        $receipt = CustomerReceipt::create(['customer_id' => $customer->id, 'receipt_number' => $number, 'receipt_date' => $date, 'currency' => 'USD', 'amount' => $amount, 'payment_method' => 'EFT', 'status' => 'draft']);
        app(CommercialPostingService::class)->postReceipt($receipt);
    }

    private function payment(string $number, string $date, float $amount): void
    {
        $supplier = Supplier::firstOrCreate(['supplier_number' => 'S1'], ['name' => 'Supplier', 'status' => true]);
        $payment = SupplierPayment::create(['supplier_id' => $supplier->id, 'payment_number' => $number, 'payment_date' => $date, 'currency' => 'USD', 'amount' => $amount, 'payment_method' => 'EFT', 'status' => 'draft']);
        app(CommercialPostingService::class)->postSupplierPayment($payment);
    }

    private function importStatement(CompanyBankAccount $bank, string $csv, float $closing): BankStatement
    {
        $this->post(route('accounting.banking.statements.store'), [
            'company_bank_account_id' => $bank->id, 'statement_date' => '2026-06-10',
            'opening_balance' => 0, 'closing_balance' => $closing,
            'file' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        ])->assertSessionHasNoErrors();

        return BankStatement::latest('id')->firstOrFail();
    }

    public function test_statement_import_auto_match_bank_charges_and_completion(): void
    {
        $bank = $this->bankSetup();
        $this->receipt('R1', '2026-06-03', 500);
        $this->payment('P1', '2026-06-05', 200);
        $this->receipt('R2', '2026-06-08', 75);   // banked late: not on this statement

        $csv = "Date,Description,Reference,Debit,Credit\n"
            ."04/06/2026,Customer deposit,R1,,500.00\n"
            ."06/06/2026,EFT Supplier,P1,200.00,\n"
            ."07/06/2026,Monthly service fee,,15.00,\n";
        $statement = $this->importStatement($bank, $csv, 285);

        $this->assertSame(3, $statement->lines()->count());
        $this->assertSame(1, $statement->lines()->whereNull('journal_line_id')->count()); // only the bank charge is left

        $this->post(route('accounting.banking.statements.complete', $statement))->assertSessionHasErrors('statement');

        $charge = $statement->lines()->whereNull('journal_line_id')->first();
        $this->post(route('accounting.banking.lines.journal', $charge), ['account_id' => $this->ledgerAccount($this->company, '6400')->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(15.0, $this->balance('6400'));
        $this->get(route('accounting.banking.statements.show', $statement))->assertOk()->assertSee('R2')->assertSee('Not yet on statement');

        $this->post(route('accounting.banking.statements.complete', $statement))->assertSessionHasNoErrors();
        $this->assertSame('reconciled', $statement->fresh()->status);
    }

    public function test_manual_match_requires_same_amount_and_accountant_cannot_post_bank_journals(): void
    {
        $bank = $this->bankSetup();
        $this->receipt('R1', '2026-06-03', 500);
        $this->receipt('R2', '2026-06-04', 500); // two candidates: auto-match must not guess

        $statement = $this->importStatement($bank, "Date,Description,Amount\n2026-06-05,Deposit,500\n2026-06-05,Interest,1.25\n", 501.25);
        $deposit = $statement->lines()->where('description', 'Deposit')->first();
        $this->assertNull($deposit->journal_line_id);

        $wrong = JournalLine::where('debit', 0)->first(); // a credit line, wrong amount/side
        $this->post(route('accounting.banking.lines.match', $deposit), ['journal_line_id' => $wrong->id])->assertSessionHasErrors('journal_line_id');

        $accountant = $this->ledgerUser($this->company, 'accountant');
        $interest = $statement->lines()->where('description', 'Interest')->first();
        $this->actingAs($accountant)
            ->post(route('accounting.banking.lines.journal', $interest), ['account_id' => $this->ledgerAccount($this->company, '4100')->id])
            ->assertForbidden();
    }

    public function test_straight_line_depreciation_catches_up_and_cannot_run_twice(): void
    {
        $asset = FixedAsset::create([
            'asset_number' => 'FA-1', 'name' => 'Laptop', 'category' => 'IT', 'acquired_on' => '2026-04-10',
            'cost' => 1200, 'salvage_value' => 0, 'useful_life_months' => 12, 'depreciation_method' => 'straight_line', 'status' => 'active',
        ]);
        $june = AccountingPeriod::where('name', 'Jun 2026')->first();

        $this->get(route('accounting.fixed-assets.depreciation.index', ['period_id' => $june->id]))->assertOk()->assertSee('300.00');
        $this->post(route('accounting.fixed-assets.depreciation.store'), ['accounting_period_id' => $june->id])->assertSessionHasNoErrors();

        $this->assertSame(300.0, $this->balance('6500'));        // April, May, June
        $this->assertSame(-300.0, $this->balance('1550'));
        $this->assertSame(900.0, $asset->fresh()->netBookValue());
        $this->assertSame('2026-06-30', $asset->fresh()->depreciated_to->toDateString());

        $this->post(route('accounting.fixed-assets.depreciation.store'), ['accounting_period_id' => $june->id])->assertSessionHasErrors('period');

        $july = AccountingPeriod::where('name', 'Jul 2026')->first();
        $this->post(route('accounting.fixed-assets.depreciation.store'), ['accounting_period_id' => $july->id])->assertSessionHasNoErrors();
        $this->assertSame(400.0, $this->balance('6500'));
    }

    public function test_declining_balance_never_goes_below_salvage_and_needs_approval_role(): void
    {
        FixedAsset::create([
            'asset_number' => 'FA-2', 'name' => 'Truck', 'category' => 'Vehicles', 'acquired_on' => '2026-06-01',
            'cost' => 1000, 'salvage_value' => 900, 'useful_life_months' => 10, 'depreciation_method' => 'declining_balance', 'status' => 'active',
        ]);
        $june = AccountingPeriod::where('name', 'Jun 2026')->first();

        $this->actingAs($this->ledgerUser($this->company, 'accountant'))
            ->post(route('accounting.fixed-assets.depreciation.store'), ['accounting_period_id' => $june->id])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('accounting.fixed-assets.depreciation.store'), ['accounting_period_id' => $june->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(100.0, $this->balance('6500')); // 1000 × 2/10 = 200, capped at cost − salvage = 100
    }
}

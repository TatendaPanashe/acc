<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\PurchaseOrder;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\SalesOrder;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\SupplierPayment;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Services\CommercialPostingService;
use App\Modules\Accounting\Services\FinancialStatementService;
use App\Modules\Accounting\Services\InventoryStockService;
use App\Modules\Accounting\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class LedgerPostingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 10:00:00');

        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers ------------------------------------------------------------

    private function account(string $code): Account
    {
        return $this->ledgerAccount($this->company, $code);
    }

    private function journal(string $date, array $lines, string $currency = 'USD'): JournalEntry
    {
        $entry = JournalEntry::create(['entry_number' => 'JV-'.uniqid(), 'entry_date' => $date, 'currency' => $currency, 'status' => 'draft']);
        foreach ($lines as [$code, $debit, $credit]) {
            $entry->lines()->create(['company_id' => $this->company->id, 'account_id' => $this->account($code)->id, 'debit' => $debit, 'credit' => $credit]);
        }

        return $entry;
    }

    private function postEntry(JournalEntry $entry): JournalEntry
    {
        return app(JournalPostingService::class)->post($entry);
    }

    private function balance(string $code, ?string $asAt = null): float
    {
        $account = $this->account($code);
        $lines = JournalLine::where('account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->when($asAt, fn ($q) => $q->whereDate('entry_date', '<=', $asAt)))
            ->get();

        return round($lines->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2);
    }

    private function customer(): Customer
    {
        return Customer::create(['customer_number' => 'C'.uniqid(), 'name' => 'Client', 'credit_limit' => 0, 'status' => true]);
    }

    private function invoice(float $amount, string $currency = 'USD', string $date = '2026-06-10'): SalesInvoice
    {
        $this->post(route('accounting.ar.invoices.store'), [
            'customer_id' => $this->customer()->id, 'invoice_number' => $number = 'INV-'.uniqid(),
            'invoice_date' => $date, 'due_date' => $date, 'currency' => $currency,
            'lines' => [['revenue_account_id' => $this->account('4000')->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
        ])->assertSessionHasNoErrors();

        $invoice = SalesInvoice::where('invoice_number', $number)->firstOrFail();
        app(CommercialPostingService::class)->postSalesInvoice($invoice);

        return $invoice->fresh();
    }

    private function addCurrency(string $code, float $rateToUsd, string $date = '2026-01-01'): void
    {
        Currency::create(['code' => $code, 'name' => $code, 'is_base' => false, 'status' => true]);
        ExchangeRate::create(['from_currency' => $code, 'to_currency' => 'USD', 'rate_date' => $date, 'rate' => $rateToUsd]);
    }

    // ---- A. periods ---------------------------------------------------------

    public function test_posting_assigns_period_from_entry_date(): void
    {
        $entry = $this->postEntry($this->journal('2026-03-20', [['6000', 50, 0], ['1000', 0, 50]]));

        $this->assertSame('Mar 2026', AccountingPeriod::find($entry->accounting_period_id)->name);
    }

    public function test_posting_into_closed_period_is_rejected(): void
    {
        AccountingPeriod::where('name', 'Mar 2026')->update(['status' => 'closed']);

        $this->expectException(ValidationException::class);
        $this->postEntry($this->journal('2026-03-20', [['6000', 50, 0], ['1000', 0, 50]]));
    }

    public function test_posting_outside_any_period_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->postEntry($this->journal('2030-01-05', [['6000', 50, 0], ['1000', 0, 50]]));
    }

    public function test_closed_period_blocks_commercial_documents_too(): void
    {
        AccountingPeriod::where('name', 'Jun 2026')->update(['status' => 'locked']);

        $this->expectException(ValidationException::class);
        $this->invoice(100);
    }

    public function test_lines_must_be_debit_or_credit_and_use_postable_accounts(): void
    {
        try {
            $this->postEntry($this->journal('2026-06-01', [['6000', 50, 50], ['1000', 0, 0]]));
            $this->fail('Expected validation error');
        } catch (ValidationException) {
        }

        $this->account('6000')->update(['allow_posting' => false]);
        $this->expectException(ValidationException::class);
        $this->postEntry($this->journal('2026-06-01', [['6000', 50, 0], ['1000', 0, 50]]));
    }

    public function test_posted_journal_cannot_be_changed(): void
    {
        $entry = $this->postEntry($this->journal('2026-06-01', [['6000', 50, 0], ['1000', 0, 50]]));

        $this->expectException(LogicException::class);
        $entry->lines->first()->update(['debit' => 999]);
    }

    public function test_period_cannot_close_with_unposted_journals(): void
    {
        $this->journal('2026-06-01', [['6000', 50, 0], ['1000', 0, 50]]);
        $june = AccountingPeriod::where('name', 'Jun 2026')->first();

        $this->delete(route('accounting.gl.periods.destroy', $june))->assertSessionHas('error');
        $this->assertSame('open', $june->fresh()->status);
    }

    public function test_balance_sheet_balances_and_splits_prior_and_current_year_earnings(): void
    {
        // Prior year activity (FY 2025 period so it can be posted).
        $prior = $this->createPriorYear();
        $this->postEntry($this->journal('2025-12-10', [['1000', 1000, 0], ['4000', 0, 1000]]));
        // Current year: capital, sales, expense.
        $this->postEntry($this->journal('2026-02-01', [['1000', 5000, 0], ['3100', 0, 5000]]));
        $this->postEntry($this->journal('2026-05-01', [['1100', 800, 0], ['4000', 0, 800]]));
        $this->postEntry($this->journal('2026-05-02', [['6000', 300, 0], ['1000', 0, 300]]));

        $report = app(FinancialStatementService::class)->balanceSheet(Carbon::parse('2026-06-15'));

        $this->assertSame(0.0, $report['difference']);
        $this->assertSame(1000.0, $report['prior_year_earnings']);
        $this->assertSame(500.0, $report['current_year_earnings']);
        $this->assertSame(6500.0, $report['asset_total']);
        $this->assertNotNull($prior);
    }

    public function test_contra_asset_reduces_total_assets(): void
    {
        $this->postEntry($this->journal('2026-02-01', [['1500', 1200, 0], ['3100', 0, 1200]]));
        $this->postEntry($this->journal('2026-03-01', [['6500', 100, 0], ['1550', 0, 100]]));

        $report = app(FinancialStatementService::class)->balanceSheet(Carbon::parse('2026-06-15'));

        $this->assertSame(1100.0, $report['asset_total']);
        $this->assertSame(0.0, $report['difference']);
    }

    // ---- B. locking & company separation ------------------------------------

    public function test_posting_the_same_invoice_twice_creates_one_entry(): void
    {
        $invoice = $this->invoice(100);
        app(CommercialPostingService::class)->postSalesInvoice($invoice);
        $this->post(route('accounting.ar.invoices.post', $invoice));

        $this->assertSame(1, JournalEntry::where('source_type', SalesInvoice::class)->where('source_id', $invoice->id)->count());
    }

    public function test_users_cannot_reference_another_companys_records(): void
    {
        $other = $this->createLedgerCompany('Other');
        $foreignAccount = $this->ledgerAccount($other, '6000');
        $foreignCustomer = new Customer(['customer_number' => 'X1', 'name' => 'Foreign', 'credit_limit' => 0, 'status' => true]);
        $foreignCustomer->company_id = $other->id;
        $foreignCustomer->saveQuietly();

        $this->post(route('accounting.gl.journals.store'), [
            'entry_number' => 'JV-X', 'entry_date' => '2026-06-01', 'currency' => 'USD',
            'lines' => [['account_id' => $foreignAccount->id, 'debit' => 10], ['account_id' => $this->account('1000')->id, 'credit' => 10]],
        ])->assertSessionHasErrors('lines.0.account_id');

        $this->post(route('accounting.ar.receipts.store'), [
            'customer_id' => $foreignCustomer->id, 'receipt_number' => 'R-X', 'receipt_date' => '2026-06-01',
            'currency' => 'USD', 'amount' => 10, 'payment_method' => 'cash',
        ])->assertSessionHasErrors('customer_id');

        $theirInvoice = new SalesInvoice(['customer_id' => $foreignCustomer->id, 'invoice_number' => 'T1', 'invoice_date' => '2026-06-01', 'due_date' => '2026-06-01', 'currency' => 'USD', 'total' => 5, 'status' => 'draft']);
        $theirInvoice->company_id = $other->id;
        $theirInvoice->saveQuietly();

        $this->get(route('accounting.ar.invoices.show', $theirInvoice))->assertNotFound();
        $this->post(route('accounting.ar.invoices.post', $theirInvoice))->assertNotFound();
    }

    public function test_receipt_cannot_settle_another_customers_invoice(): void
    {
        $invoice = $this->invoice(100);
        $this->post(route('accounting.ar.receipts.store'), [
            'customer_id' => $this->customer()->id, 'invoice_id' => $invoice->id, 'receipt_number' => 'R-1',
            'receipt_date' => '2026-06-12', 'currency' => 'USD', 'amount' => 100, 'payment_method' => 'cash', 'post_now' => 1,
        ])->assertSessionHasErrors('invoice_id');
    }

    public function test_accountant_cannot_post_supplier_payment_on_capture(): void
    {
        $supplier = Supplier::create(['supplier_number' => 'S1', 'name' => 'Supplier', 'status' => true]);

        $this->actingAs($this->ledgerUser($this->company, 'accountant'))->post(route('accounting.ap.payments.store'), [
            'supplier_id' => $supplier->id, 'payment_number' => 'P-1', 'payment_date' => '2026-06-12',
            'currency' => 'USD', 'amount' => 50, 'payment_method' => 'bank', 'post_now' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('draft', SupplierPayment::where('payment_number', 'P-1')->value('status'));
    }

    // ---- C. account mapping & inventory -------------------------------------

    public function test_account_mapping_redirects_postings(): void
    {
        $ecocash = Account::create(['code' => '1020', 'name' => 'EcoCash', 'type' => 'asset', 'normal_balance' => 'debit', 'allow_posting' => true, 'status' => true]);
        $this->put(route('accounting.settings.mappings.update'), ['mappings' => ['mobile_money' => $ecocash->id]])->assertSessionHasNoErrors();

        $invoice = $this->invoice(80);
        $this->post(route('accounting.ar.receipts.store'), [
            'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'receipt_number' => 'R-2',
            'receipt_date' => '2026-06-12', 'currency' => 'USD', 'amount' => 80, 'payment_method' => 'EcoCash', 'post_now' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(80.0, $this->balance('1020'));
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, AccountMapping::count());
    }

    public function test_inventory_receipts_and_issues_post_at_weighted_average_cost(): void
    {
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main', 'status' => true]);
        $item = Item::create(['sku' => 'W1', 'name' => 'Widget', 'type' => 'stock', 'costing_method' => 'weighted_average', 'standard_cost' => 1, 'selling_price' => 20, 'status' => true]);
        $supplier = Supplier::create(['supplier_number' => 'S1', 'name' => 'Supplier', 'status' => true]);

        foreach ([[10, 5], [10, 7]] as $i => [$qty, $cost]) {
            $this->post(route('accounting.inventory.purchase-orders.store'), [
                'supplier_id' => $supplier->id, 'order_number' => 'PO-'.$i, 'order_date' => '2026-06-01', 'currency' => 'USD',
                'lines' => [['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => $qty, 'unit_cost' => $cost]],
            ])->assertSessionHasNoErrors();
            $order = PurchaseOrder::where('order_number', 'PO-'.$i)->first();
            $this->post(route('accounting.inventory.purchase-orders.approve', $order));
            $this->post(route('accounting.inventory.purchase-orders.receive', $order))->assertSessionHasNoErrors();
        }

        $this->assertSame(120.0, $this->balance('1200'));
        $this->assertSame(-120.0, $this->balance('2050'));

        $this->post(route('accounting.inventory.sales-orders.store'), [
            'customer_id' => $this->customer()->id, 'order_number' => 'SO-1', 'order_date' => '2026-06-05', 'currency' => 'USD',
            'lines' => [['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5, 'unit_price' => 20]],
        ])->assertSessionHasNoErrors();
        $order = SalesOrder::where('order_number', 'SO-1')->first();
        $this->post(route('accounting.inventory.sales-orders.confirm', $order));
        $this->post(route('accounting.inventory.sales-orders.fulfill', $order))->assertSessionHasNoErrors();

        $this->assertSame(30.0, $this->balance('5000'));       // 5 × average 6.00
        $this->assertSame(90.0, $this->balance('1200'));
        $this->assertSame(90.0, app(InventoryStockService::class)->totalValue()); // stock ledger agrees with GL
    }

    // ---- D. multi-currency --------------------------------------------------

    public function test_foreign_invoice_is_converted_and_keeps_transaction_amounts(): void
    {
        $this->addCurrency('ZWG', 0.04);

        $invoice = $this->invoice(1000, 'ZWG');
        $entry = JournalEntry::find($invoice->journal_entry_id);
        $arLine = $entry->lines->firstWhere('account_id', $this->account('1100')->id);

        $this->assertSame('ZWG', $entry->currency);
        $this->assertEquals(0.04, (float) $entry->exchange_rate);
        $this->assertSame(1000.0, (float) $arLine->fx_debit);
        $this->assertSame(40.0, (float) $arLine->debit);
    }

    public function test_receipt_at_a_different_rate_posts_realised_exchange_difference(): void
    {
        $this->addCurrency('ZWG', 0.04);
        $invoice = $this->invoice(1000, 'ZWG', '2026-06-01');
        ExchangeRate::create(['from_currency' => 'ZWG', 'to_currency' => 'USD', 'rate_date' => '2026-06-12', 'rate' => 0.035]);

        $this->post(route('accounting.ar.receipts.store'), [
            'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'receipt_number' => 'R-FX',
            'receipt_date' => '2026-06-12', 'currency' => 'ZWG', 'amount' => 1000, 'payment_method' => 'bank', 'post_now' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, $this->balance('1100'));   // receivable fully cleared in base currency
        $this->assertSame(35.0, $this->balance('1000'));  // cash received at today's rate
        $this->assertSame(5.0, $this->balance('6600'));   // realised exchange loss
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_missing_exchange_rate_blocks_posting(): void
    {
        Currency::create(['code' => 'ZAR', 'name' => 'Rand', 'is_base' => false, 'status' => true]);

        $this->expectException(ValidationException::class);
        $this->invoice(100, 'ZAR');
    }

    public function test_manual_foreign_journal_is_converted_at_posting(): void
    {
        $this->addCurrency('ZWG', 0.04);
        $entry = $this->postEntry($this->journal('2026-06-01', [['6000', 250, 0], ['1000', 0, 250]], 'ZWG'));

        $line = $entry->lines->firstWhere('account_id', $this->account('6000')->id);
        $this->assertSame(250.0, (float) $line->fx_debit);
        $this->assertSame(10.0, (float) $line->debit);
    }

    public function test_base_currency_is_locked_after_posting(): void
    {
        $this->postEntry($this->journal('2026-06-01', [['6000', 50, 0], ['1000', 0, 50]]));

        $this->post(route('accounting.settings.currencies.store'), ['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'is_base' => 1])
            ->assertSessionHasErrors('is_base');
    }

    public function test_report_and_settings_pages_render_with_data(): void
    {
        $this->addCurrency('ZWG', 0.04);
        $this->invoice(250);
        $this->postEntry($this->journal('2026-06-02', [['6000', 40, 0], ['1000', 0, 40]]));
        $june = AccountingPeriod::where('name', 'Jun 2026')->first();

        foreach ([
            route('accounting.gl.trial-balance'), route('accounting.gl.trial-balance', ['period_id' => $june->id]),
            route('accounting.gl.profit-and-loss'), route('accounting.gl.profit-and-loss', ['period_id' => $june->id]),
            route('accounting.gl.balance-sheet'), route('accounting.gl.cash-flow'),
            route('accounting.settings.index'), route('accounting.inventory.overview'), route('accounting.banking.index'),
            route('accounting.fixed-assets.depreciation.index'), route('accounting.gl.journals.create'), route('dashboard'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('accounting.gl.balance-sheet'))->assertSee('Balanced');
        $this->get(route('accounting.gl.profit-and-loss'))->assertSee('210.00'); // 250 revenue - 40 expense
        $this->get(route('accounting.gl.statutory.download', 'statutory-pack'))->assertOk();
    }

    public function test_pos_terminal_includes_product_details_and_store_stock(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Main', 'status' => true]);
        $branch = Warehouse::create(['code' => 'BR2', 'name' => 'Branch', 'status' => true]);
        $item = Item::create(['sku' => 'TEA-1', 'name' => 'Rooibos Tea', 'type' => 'stock', 'costing_method' => 'weighted_average', 'standard_cost' => 1, 'selling_price' => 4.5, 'reorder_level' => 3, 'status' => true]);
        $stock = app(InventoryStockService::class);
        $stock->move($this->company->id, $item->id, $main->id, 'opening', 12, 1.0);
        $stock->move($this->company->id, $item->id, $branch->id, 'opening', 5, 1.0);
        \App\Modules\Accounting\Models\TaxCode::create(['code' => 'VAT-STD', 'name' => 'Standard VAT', 'type' => 'both', 'rate' => 15.5, 'status' => true]);

        $response = $this->get(route('accounting.pos.create'))->assertOk()->assertSee('Selected');
        $payload = collect(json_decode(str($response->getContent())->betweenFirst('const posItems = ', ";\n")->value(), true));
        $tea = $payload->firstWhere('sku', 'TEA-1');

        $this->assertSame('Rooibos Tea', $tea['name']);
        $this->assertEquals(4.5, $tea['price']);
        $this->assertEquals(15.5, $tea['tax']);
        $this->assertEquals(3, $tea['reorderLevel']);
        $this->assertEquals(['MAIN' => 12, 'BR2' => 5], [
            'MAIN' => $tea['stock'][(string) $main->id],
            'BR2' => $tea['stock'][(string) $branch->id],
        ]);
    }

    private function itemForm(array $overrides = []): array
    {
        return $overrides + [
            'sku' => 'SKU-'.uniqid(), 'name' => 'Sugar 2kg', 'type' => 'stock', 'costing_method' => 'weighted_average',
            'standard_cost' => 2.5, 'selling_price' => 4, 'reorder_level' => 5,
        ];
    }

    public function test_new_product_can_be_added_with_quantities_per_store(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Main', 'status' => true]);
        $branch = Warehouse::create(['code' => 'BR2', 'name' => 'Branch', 'status' => true]);

        $this->get(route('accounting.inventory.items.index'))->assertOk()->assertSee('Quantity on hand')->assertSee('opening['.$main->id.']', false);

        $this->post(route('accounting.inventory.items.store'), $this->itemForm([
            'sku' => 'SUG-2', 'opening' => [$main->id => 10, $branch->id => 4], 'opening_date' => '2026-06-01',
        ]))->assertSessionHasNoErrors();

        $item = Item::where('sku', 'SUG-2')->firstOrFail();
        $stock = app(InventoryStockService::class);
        $this->assertSame(10.0, $stock->available($item->id, $main->id));
        $this->assertSame(4.0, $stock->available($item->id, $branch->id));
        $this->assertSame(35.0, $this->balance('1200'));    // 14 × standard cost 2.50
        $this->assertSame(-35.0, $this->balance('3900'));
        $this->assertSame(35.0, $stock->totalValue());

        $this->get(route('accounting.inventory.items.index'))->assertSee('>14<', false);
        // Once stock has moved, opening quantities can no longer be added from the item form.
        $this->get(route('accounting.inventory.items.edit', $item))->assertOk()->assertDontSee('Quantity on hand');
        $this->patch(route('accounting.inventory.items.update', $item), $this->itemForm(['sku' => 'SUG-2', 'opening' => [$main->id => 1]]))
            ->assertSessionHasErrors('opening');
    }

    public function test_existing_product_without_stock_can_get_opening_quantities_at_a_given_cost(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Main', 'status' => true]);
        $this->post(route('accounting.inventory.items.store'), $this->itemForm(['sku' => 'OIL-1']))->assertSessionHasNoErrors();
        $item = Item::where('sku', 'OIL-1')->firstOrFail();

        $this->get(route('accounting.inventory.items.edit', $item))->assertSee('Quantity on hand');
        $this->patch(route('accounting.inventory.items.update', $item), $this->itemForm([
            'sku' => 'OIL-1', 'opening' => [$main->id => 8], 'opening_unit_cost' => 3,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(24.0, $this->balance('1200'));
    }

    public function test_only_approvers_enter_quantities_and_only_for_stock_items(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Main', 'status' => true]);

        $this->post(route('accounting.inventory.items.store'), $this->itemForm(['type' => 'service', 'opening' => [$main->id => 3]]))
            ->assertSessionHasErrors('opening');

        $accountant = $this->ledgerUser($this->company, 'accountant');
        $this->actingAs($accountant)->get(route('accounting.inventory.items.index'))->assertSee('entered by a Finance Manager');
        $this->actingAs($accountant)->post(route('accounting.inventory.items.store'), $this->itemForm(['sku' => 'ACC-1', 'opening' => [$main->id => 3]]))
            ->assertSessionHasErrors('opening');
        $this->assertFalse(Item::where('sku', 'ACC-1')->exists());

        $this->actingAs($accountant)->post(route('accounting.inventory.items.store'), $this->itemForm(['sku' => 'ACC-2']))->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->balance('1200'));
    }

    private function createPriorYear(): \App\Modules\Accounting\Models\FiscalYear
    {
        $year = \App\Modules\Accounting\Models\FiscalYear::create(['name' => 'FY 2025', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'open']);
        AccountingPeriod::create(['fiscal_year_id' => $year->id, 'name' => 'Dec 2025', 'starts_on' => '2025-12-01', 'ends_on' => '2025-12-31', 'status' => 'open']);

        return $year;
    }
}

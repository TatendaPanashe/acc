<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Demo Enterprise'],
            ['status' => true]
        );

        DB::table('accounting_currencies')->updateOrInsert(
            ['company_id' => $company->id, 'code' => 'USD'],
            [
                'name' => 'United States Dollar',
                'symbol' => '$',
                'is_base' => true,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_currencies')->updateOrInsert(
            ['company_id' => $company->id, 'code' => 'ZWL'],
            [
                'name' => 'Zimbabwe Gold',
                'symbol' => 'ZWL',
                'is_base' => false,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_exchange_rates')->updateOrInsert(
            [
                'company_id' => $company->id,
                'from_currency' => 'ZWL',
                'to_currency' => 'USD',
                'rate_date' => now()->toDateString(),
            ],
            [
                'rate' => 0.04,
                'source' => 'Manual seed rate',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $year = now()->year;
        DB::table('accounting_fiscal_years')->updateOrInsert(
            ['company_id' => $company->id, 'name' => 'FY '.$year],
            [
                'starts_on' => $year.'-01-01',
                'ends_on' => $year.'-12-31',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $fiscalYearId = DB::table('accounting_fiscal_years')
            ->where('company_id', $company->id)
            ->where('name', 'FY '.$year)
            ->value('id');

        for ($month = 1; $month <= 12; $month++) {
            $startsOn = now()->setYear($year)->setMonth($month)->startOfMonth();
            DB::table('accounting_periods')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'fiscal_year_id' => $fiscalYearId,
                    'name' => $startsOn->format('M Y'),
                ],
                [
                    'starts_on' => $startsOn->toDateString(),
                    'ends_on' => $startsOn->copy()->endOfMonth()->toDateString(),
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $accounts = [
            ['1000', 'Cash and Bank', 'asset', 'debit', true],
            ['1100', 'Accounts Receivable', 'asset', 'debit', true],
            ['1200', 'Inventory', 'asset', 'debit', true],
            ['1500', 'Fixed Assets', 'asset', 'debit', true],
            ['2000', 'Accounts Payable', 'liability', 'credit', true],
            ['2100', 'VAT Payable', 'liability', 'credit', true],
            ['3000', 'Retained Earnings', 'equity', 'credit', false],
            ['4000', 'Sales Revenue', 'revenue', 'credit', false],
            ['5000', 'Cost of Goods Sold', 'expense', 'debit', false],
            ['6000', 'Operating Expenses', 'expense', 'debit', false],
        ];

        foreach ($accounts as [$code, $name, $type, $normalBalance, $control]) {
            Account::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'normal_balance' => $normalBalance,
                    'is_control_account' => $control,
                    'allow_posting' => true,
                    'status' => true,
                ]
            );
        }

        DB::table('accounting_tax_codes')->updateOrInsert(
            ['company_id' => $company->id, 'code' => 'VAT15'],
            [
                'name' => 'VAT 15%',
                'type' => 'both',
                'rate' => 15,
                'control_account_id' => Account::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('code', '2100')
                    ->value('id'),
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_customers')->updateOrInsert(
            ['company_id' => $company->id, 'customer_number' => 'CUS-0001'],
            [
                'name' => 'Blue Valley Retail',
                'email' => 'accounts@bluevalley.example',
                'phone' => '+263 242 555 010',
                'billing_address' => '12 Enterprise Road, Harare',
                'credit_limit' => 15000,
                'currency' => 'USD',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_customers')->updateOrInsert(
            ['company_id' => $company->id, 'customer_number' => 'CUS-0002'],
            [
                'name' => 'Northern Foods Group',
                'email' => 'finance@northernfoods.example',
                'phone' => '+263 867 700 220',
                'billing_address' => '4 Industrial Crescent, Bulawayo',
                'credit_limit' => 25000,
                'currency' => 'USD',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_suppliers')->updateOrInsert(
            ['company_id' => $company->id, 'supplier_number' => 'SUP-0001'],
            [
                'name' => 'Prime Office Supplies',
                'email' => 'billing@primeoffice.example',
                'phone' => '+263 242 555 019',
                'remittance_address' => '88 Samora Machel Avenue, Harare',
                'currency' => 'USD',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_suppliers')->updateOrInsert(
            ['company_id' => $company->id, 'supplier_number' => 'SUP-0002'],
            [
                'name' => 'Metro Hardware Distributors',
                'email' => 'statements@metrohardware.example',
                'phone' => '+263 292 555 300',
                'remittance_address' => '19 Kelvin Road, Bulawayo',
                'currency' => 'USD',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_warehouses')->updateOrInsert(
            ['company_id' => $company->id, 'code' => 'MAIN'],
            [
                'name' => 'Main Warehouse',
                'location' => 'Harare',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_warehouses')->updateOrInsert(
            ['company_id' => $company->id, 'code' => 'BUL'],
            [
                'name' => 'Bulawayo Branch Store',
                'location' => 'Bulawayo',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $items = [
            ['LAP-STD-14', '14-inch Business Laptop', 'stock', 'weighted_average', 620, 850, 5, true, false],
            ['PRN-THERMAL', 'Thermal Receipt Printer', 'stock', 'fifo', 140, 225, 10, true, false],
            ['CONS-SETUP', 'Implementation Consulting', 'service', 'standard', 0, 75, 0, false, false],
        ];

        foreach ($items as [$sku, $name, $type, $costing, $cost, $price, $reorder, $serials, $lots]) {
            DB::table('accounting_items')->updateOrInsert(
                ['company_id' => $company->id, 'sku' => $sku],
                [
                    'name' => $name,
                    'type' => $type,
                    'costing_method' => $costing,
                    'standard_cost' => $cost,
                    'selling_price' => $price,
                    'reorder_level' => $reorder,
                    'track_serials' => $serials,
                    'track_lots' => $lots,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $customerId = DB::table('accounting_customers')
            ->where('company_id', $company->id)
            ->where('customer_number', 'CUS-0001')
            ->value('id');
        $supplierId = DB::table('accounting_suppliers')
            ->where('company_id', $company->id)
            ->where('supplier_number', 'SUP-0001')
            ->value('id');
        $itemId = DB::table('accounting_items')
            ->where('company_id', $company->id)
            ->where('sku', 'LAP-STD-14')
            ->value('id');
        $warehouseId = DB::table('accounting_warehouses')
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->value('id');
        $branchWarehouseId = DB::table('accounting_warehouses')
            ->where('company_id', $company->id)
            ->where('code', 'BUL')
            ->value('id');
        $periodId = DB::table('accounting_periods')
            ->where('company_id', $company->id)
            ->where('starts_on', '<=', now()->toDateString())
            ->where('ends_on', '>=', now()->toDateString())
            ->value('id');

        DB::table('accounting_stock_movements')->updateOrInsert(
            [
                'company_id' => $company->id,
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'opening_balance',
            ],
            [
                'quantity' => 12,
                'unit_cost' => 620,
                'moved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_sales_orders')->updateOrInsert(
            ['company_id' => $company->id, 'order_number' => 'SO-1001'],
            [
                'customer_id' => $customerId,
                'order_date' => now()->toDateString(),
                'required_date' => now()->addDays(7)->toDateString(),
                'currency' => 'USD',
                'subtotal' => 1700,
                'tax_total' => 255,
                'total' => 1955,
                'status' => 'confirmed',
                'notes' => 'Seed sales order awaiting fulfillment.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $salesOrderId = DB::table('accounting_sales_orders')
            ->where('company_id', $company->id)
            ->where('order_number', 'SO-1001')
            ->value('id');

        DB::table('accounting_sales_order_lines')->updateOrInsert(
            ['sales_order_id' => $salesOrderId, 'item_id' => $itemId],
            [
                'company_id' => $company->id,
                'warehouse_id' => $warehouseId,
                'description' => 'Business laptops for rollout',
                'quantity' => 2,
                'unit_price' => 850,
                'tax_rate' => 15,
                'line_total' => 1700,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_purchase_orders')->updateOrInsert(
            ['company_id' => $company->id, 'order_number' => 'PO-1001'],
            [
                'supplier_id' => $supplierId,
                'order_date' => now()->toDateString(),
                'expected_date' => now()->addDays(10)->toDateString(),
                'currency' => 'USD',
                'subtotal' => 3100,
                'tax_total' => 465,
                'total' => 3565,
                'status' => 'approved',
                'notes' => 'Seed purchase order ready to receive.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $purchaseOrderId = DB::table('accounting_purchase_orders')
            ->where('company_id', $company->id)
            ->where('order_number', 'PO-1001')
            ->value('id');

        DB::table('accounting_purchase_order_lines')->updateOrInsert(
            ['purchase_order_id' => $purchaseOrderId, 'item_id' => $itemId],
            [
                'company_id' => $company->id,
                'warehouse_id' => $warehouseId,
                'description' => 'Laptop replenishment',
                'quantity' => 5,
                'unit_cost' => 620,
                'tax_rate' => 15,
                'line_total' => 3100,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_transfer_orders')->updateOrInsert(
            ['company_id' => $company->id, 'transfer_number' => 'TO-1001'],
            [
                'from_warehouse_id' => $warehouseId,
                'to_warehouse_id' => $branchWarehouseId,
                'transfer_date' => now()->toDateString(),
                'status' => 'draft',
                'notes' => 'Seed branch stock transfer.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $transferOrderId = DB::table('accounting_transfer_orders')
            ->where('company_id', $company->id)
            ->where('transfer_number', 'TO-1001')
            ->value('id');

        DB::table('accounting_transfer_order_lines')->updateOrInsert(
            ['transfer_order_id' => $transferOrderId, 'item_id' => $itemId],
            [
                'company_id' => $company->id,
                'quantity' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_stores_requisitions')->updateOrInsert(
            ['company_id' => $company->id, 'requisition_number' => 'SR-1001'],
            [
                'warehouse_id' => $warehouseId,
                'requested_by' => User::where('email', 'finance@example.com')->value('id'),
                'department' => 'Operations',
                'required_date' => now()->addDays(2)->toDateString(),
                'status' => 'draft',
                'purpose' => 'Seed internal issue request.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $storesRequisitionId = DB::table('accounting_stores_requisitions')
            ->where('company_id', $company->id)
            ->where('requisition_number', 'SR-1001')
            ->value('id');

        DB::table('accounting_stores_requisition_lines')->updateOrInsert(
            ['stores_requisition_id' => $storesRequisitionId, 'item_id' => $itemId],
            [
                'company_id' => $company->id,
                'description' => 'Operations laptop issue',
                'quantity_requested' => 1,
                'quantity_issued' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_pos_sales')->updateOrInsert(
            ['company_id' => $company->id, 'receipt_number' => 'POS-1001'],
            [
                'customer_id' => null,
                'warehouse_id' => $warehouseId,
                'cashier_id' => User::where('email', 'finance@example.com')->value('id'),
                'sold_at' => now()->subHour(),
                'currency' => 'USD',
                'payment_method' => 'cash',
                'subtotal' => 225,
                'tax_total' => 33.75,
                'discount_total' => 0,
                'total' => 258.75,
                'amount_tendered' => 260,
                'change_due' => 1.25,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $posSaleId = DB::table('accounting_pos_sales')
            ->where('company_id', $company->id)
            ->where('receipt_number', 'POS-1001')
            ->value('id');

        $printerItemId = DB::table('accounting_items')
            ->where('company_id', $company->id)
            ->where('sku', 'PRN-THERMAL')
            ->value('id');

        DB::table('accounting_pos_sale_lines')->updateOrInsert(
            ['pos_sale_id' => $posSaleId, 'item_id' => $printerItemId],
            [
                'company_id' => $company->id,
                'description' => 'Thermal Receipt Printer',
                'quantity' => 1,
                'unit_price' => 225,
                'discount' => 0,
                'tax_rate' => 15,
                'line_total' => 225,
                'tax_amount' => 33.75,
                'cost_total' => 140,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_stock_movements')->updateOrInsert(
            [
                'company_id' => $company->id,
                'item_id' => $printerItemId,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'pos_sale',
                'source_type' => 'App\\Modules\\Accounting\\Models\\PosSale',
                'source_id' => $posSaleId,
            ],
            [
                'quantity' => -1,
                'unit_cost' => 140,
                'moved_at' => now()->subHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_journal_entries')->updateOrInsert(
            ['company_id' => $company->id, 'entry_number' => 'JNL-OPEN-001'],
            [
                'accounting_period_id' => $periodId,
                'entry_date' => now()->toDateString(),
                'source_module' => 'general_ledger',
                'currency' => 'USD',
                'exchange_rate' => 1,
                'memo' => 'Opening balances for acc263 demo company.',
                'status' => 'posted',
                'created_by' => User::where('email', 'finance@example.com')->value('id'),
                'posted_by' => User::where('email', 'finance@example.com')->value('id'),
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $journalId = DB::table('accounting_journal_entries')
            ->where('company_id', $company->id)
            ->where('entry_number', 'JNL-OPEN-001')
            ->value('id');

        DB::table('accounting_journal_lines')->where('journal_entry_id', $journalId)->delete();
        $lineAccounts = [
            ['1000', 'Opening cash balance', 30000, 0],
            ['1200', 'Opening inventory balance', 7440, 0],
            ['3000', 'Opening equity', 0, 37440],
        ];

        foreach ($lineAccounts as [$code, $description, $debit, $credit]) {
            DB::table('accounting_journal_lines')->insert([
                'company_id' => $company->id,
                'journal_entry_id' => $journalId,
                'account_id' => Account::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('code', $code)
                    ->value('id'),
                'description' => $description,
                'debit' => $debit,
                'credit' => $credit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            ['4000', 45000, 'Monthly sales target'],
            ['5000', 21000, 'Expected cost of sales'],
            ['6000', 9500, 'Operating expense allowance'],
        ] as [$code, $amount, $notes]) {
            DB::table('accounting_budgets')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'accounting_period_id' => $periodId,
                    'account_id' => Account::withoutGlobalScopes()
                        ->where('company_id', $company->id)
                        ->where('code', $code)
                        ->value('id'),
                ],
                [
                    'currency' => 'USD',
                    'amount' => $amount,
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('accounting_invoices')->updateOrInsert(
            ['company_id' => $company->id, 'invoice_number' => 'INV-1001'],
            [
                'customer_id' => $customerId,
                'invoice_date' => now()->subDays(10)->toDateString(),
                'due_date' => now()->addDays(20)->toDateString(),
                'currency' => 'USD',
                'subtotal' => 2500,
                'tax_total' => 375,
                'total' => 2875,
                'amount_paid' => 1000,
                'status' => 'part_paid',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $salesInvoiceId = DB::table('accounting_invoices')
            ->where('company_id', $company->id)
            ->where('invoice_number', 'INV-1001')
            ->value('id');

        DB::table('accounting_invoice_lines')->updateOrInsert(
            ['invoice_id' => $salesInvoiceId, 'description' => 'Implementation consulting'],
            [
                'company_id' => $company->id,
                'revenue_account_id' => Account::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('code', '4000')
                    ->value('id'),
                'quantity' => 20,
                'unit_price' => 125,
                'tax_rate' => 15,
                'line_total' => 2500,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_customer_receipts')->updateOrInsert(
            ['company_id' => $company->id, 'receipt_number' => 'RCT-1000'],
            [
                'customer_id' => $customerId,
                'invoice_id' => $salesInvoiceId,
                'receipt_date' => now()->subDays(3)->toDateString(),
                'currency' => 'USD',
                'amount' => 1000,
                'payment_method' => 'bank',
                'memo' => 'Partial payment against INV-1001.',
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $receiptId = DB::table('accounting_customer_receipts')
            ->where('company_id', $company->id)
            ->where('receipt_number', 'RCT-1000')
            ->value('id');

        DB::table('accounting_customer_allocations')->updateOrInsert(
            ['receipt_id' => $receiptId, 'invoice_id' => $salesInvoiceId],
            [
                'company_id' => $company->id,
                'amount' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_supplier_invoices')->updateOrInsert(
            ['company_id' => $company->id, 'supplier_id' => $supplierId, 'invoice_number' => 'PINV-7781'],
            [
                'invoice_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(25)->toDateString(),
                'currency' => 'USD',
                'subtotal' => 940,
                'tax_total' => 141,
                'total' => 1081,
                'amount_paid' => 0,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $supplierInvoiceId = DB::table('accounting_supplier_invoices')
            ->where('company_id', $company->id)
            ->where('supplier_id', $supplierId)
            ->where('invoice_number', 'PINV-7781')
            ->value('id');

        DB::table('accounting_supplier_invoice_lines')->updateOrInsert(
            ['supplier_invoice_id' => $supplierInvoiceId, 'description' => 'Office supplies and consumables'],
            [
                'company_id' => $company->id,
                'expense_account_id' => Account::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('code', '6000')
                    ->value('id'),
                'quantity' => 1,
                'unit_cost' => 940,
                'tax_rate' => 15,
                'line_total' => 940,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_supplier_payments')->updateOrInsert(
            ['company_id' => $company->id, 'payment_number' => 'PAY-DRAFT-1001'],
            [
                'supplier_id' => $supplierId,
                'supplier_invoice_id' => $supplierInvoiceId,
                'payment_date' => now()->toDateString(),
                'currency' => 'USD',
                'amount' => 500,
                'payment_method' => 'eft',
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_bank_statements')->updateOrInsert(
            ['company_id' => $company->id, 'bank_account_name' => 'Operating Bank', 'statement_date' => now()->toDateString()],
            [
                'format' => 'CSV',
                'file_path' => null,
                'status' => 'imported',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_fixed_assets')->updateOrInsert(
            ['company_id' => $company->id, 'asset_number' => 'FA-0001'],
            [
                'name' => 'Delivery Vehicle',
                'category' => 'Motor Vehicles',
                'acquired_on' => now()->subMonths(4)->toDateString(),
                'cost' => 18500,
                'salvage_value' => 2500,
                'useful_life_months' => 60,
                'depreciation_method' => 'straight_line',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_opportunities')->updateOrInsert(
            ['company_id' => $company->id, 'name' => 'Blue Valley POS Rollout'],
            [
                'customer_id' => $customerId,
                'stage' => 'proposal',
                'value' => 12500,
                'expected_close_on' => now()->addDays(30)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_approval_requests')->updateOrInsert(
            ['company_id' => $company->id, 'workflow_key' => 'journal.posting', 'approvable_type' => 'demo', 'approvable_id' => $journalId],
            [
                'status' => 'approved',
                'requested_by' => User::where('email', 'finance@example.com')->value('id'),
                'resolved_by' => User::where('email', 'superuser@superuser.com')->value('id'),
                'resolved_at' => now(),
                'resolution_note' => 'Seeded approval for opening journal.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('accounting_audit_logs')
            ->where('company_id', $company->id)
            ->where('auditable_type', 'accounting_seed')
            ->where('event', 'demo_data.seeded')
            ->delete();

        DB::table('accounting_audit_logs')->insert([
            'company_id' => $company->id,
            'user_id' => User::where('email', 'finance@example.com')->value('id'),
            'auditable_type' => 'accounting_seed',
            'auditable_id' => $company->id,
            'event' => 'demo_data.seeded',
            'old_values' => null,
            'new_values' => json_encode(['app' => 'acc263', 'company' => $company->name]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'DatabaseSeeder',
            'created_at' => now(),
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('status', ['open', 'locked', 'closed'])->default('open');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('accounting_fiscal_years')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('status', ['open', 'locked', 'closed'])->default('open');
            $table->timestamps();
            $table->unique(['company_id', 'fiscal_year_id', 'name']);
        });

        Schema::create('accounting_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 3);
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('accounting_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->date('rate_date');
            $table->decimal('rate', 18, 8);
            $table->string('source')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'from_currency', 'to_currency', 'rate_date'], 'acct_fx_unique');
        });

        Schema::create('accounting_tax_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('type', ['sales', 'purchase', 'both'])->default('both');
            $table->decimal('rate', 8, 4)->default(0);
            $table->foreignId('control_account_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->boolean('is_control_account')->default(false);
            $table->boolean('allow_posting')->default(true);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::table('accounting_tax_codes', function (Blueprint $table) {
            $table->foreign('control_account_id')->references('id')->on('accounting_accounts')->nullOnDelete();
        });

        Schema::create('accounting_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('customer_number', 40);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('billing_address')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'customer_number']);
        });

        Schema::create('accounting_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('supplier_number', 40);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('remittance_address')->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'supplier_number']);
        });

        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            $table->string('entry_number', 60);
            $table->date('entry_date');
            $table->string('source_module', 60)->default('general_ledger');
            $table->nullableMorphs('source');
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->text('memo')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'posted', 'reversed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'entry_number']);
        });

        Schema::create('accounting_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('tax_code')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('currency', 3);
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'accounting_period_id', 'account_id'], 'acct_budget_unique');
        });

        Schema::create('accounting_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('accounting_customers')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->string('invoice_number', 60);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('currency', 3);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'posted', 'part_paid', 'paid', 'void'])->default('draft');
            $table->timestamps();
            $table->unique(['company_id', 'invoice_number']);
        });

        Schema::create('accounting_supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('accounting_suppliers')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->string('invoice_number', 80);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('currency', 3);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'posted', 'part_paid', 'paid', 'void'])->default('draft');
            $table->timestamps();
        });

        Schema::create('accounting_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('accounting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('name');
            $table->enum('type', ['stock', 'service', 'non_stock'])->default('stock');
            $table->enum('costing_method', ['fifo', 'weighted_average', 'standard'])->default('weighted_average');
            $table->decimal('standard_cost', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('reorder_level', 14, 4)->default(0);
            $table->boolean('track_serials')->default(false);
            $table->boolean('track_lots')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
        });

        Schema::create('accounting_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
            $table->string('movement_type', 40);
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->nullableMorphs('source');
            $table->timestamp('moved_at');
            $table->timestamps();
        });

        Schema::create('accounting_bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('bank_account_name');
            $table->string('format', 20);
            $table->date('statement_date');
            $table->string('file_path')->nullable();
            $table->enum('status', ['imported', 'matched', 'reconciled'])->default('imported');
            $table->timestamps();
        });

        Schema::create('accounting_fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('asset_number', 60);
            $table->string('name');
            $table->string('category');
            $table->date('acquired_on');
            $table->decimal('cost', 14, 2);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->enum('depreciation_method', ['straight_line', 'declining_balance']);
            $table->enum('status', ['active', 'disposed', 'transferred', 'retired'])->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'asset_number']);
        });

        Schema::create('accounting_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('accounting_customers')->nullOnDelete();
            $table->string('name');
            $table->string('stage')->default('prospecting');
            $table->decimal('value', 14, 2)->default(0);
            $table->date('expected_close_on')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->nullableMorphs('approvable');
            $table->string('workflow_key', 80);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('auditable');
            $table->string('event', 80);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_logs');
        Schema::dropIfExists('accounting_approval_requests');
        Schema::dropIfExists('accounting_opportunities');
        Schema::dropIfExists('accounting_fixed_assets');
        Schema::dropIfExists('accounting_bank_statements');
        Schema::dropIfExists('accounting_stock_movements');
        Schema::dropIfExists('accounting_items');
        Schema::dropIfExists('accounting_warehouses');
        Schema::dropIfExists('accounting_supplier_invoices');
        Schema::dropIfExists('accounting_invoices');
        Schema::dropIfExists('accounting_budgets');
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::table('accounting_tax_codes', fn (Blueprint $table) => $table->dropForeign(['control_account_id']));
        Schema::dropIfExists('accounting_suppliers');
        Schema::dropIfExists('accounting_customers');
        Schema::dropIfExists('accounting_accounts');
        Schema::dropIfExists('accounting_tax_codes');
        Schema::dropIfExists('accounting_exchange_rates');
        Schema::dropIfExists('accounting_currencies');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_fiscal_years');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounting_invoice_lines')) {
            Schema::create('accounting_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('invoice_id')->constrained('accounting_invoices')->cascadeOnDelete();
                $table->foreignId('revenue_account_id')->constrained('accounting_accounts')->restrictOnDelete();
                $table->string('description');
                $table->decimal('quantity', 14, 4)->default(1);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounting_credit_notes')) {
            Schema::create('accounting_credit_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('accounting_customers')->restrictOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('accounting_invoices')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
                $table->string('credit_note_number', 60);
                $table->date('credit_note_date');
                $table->string('currency', 3);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->text('reason')->nullable();
                $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
                $table->timestamps();
                $table->unique(['company_id', 'credit_note_number']);
            });
        }

        if (!Schema::hasTable('accounting_customer_receipts')) {
            Schema::create('accounting_customer_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('accounting_customers')->restrictOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('accounting_invoices')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
                $table->string('receipt_number', 60);
                $table->date('receipt_date');
                $table->string('currency', 3);
                $table->decimal('amount', 14, 2);
                $table->string('payment_method')->default('bank');
                $table->text('memo')->nullable();
                $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
                $table->timestamps();
                $table->unique(['company_id', 'receipt_number']);
            });
        }

        if (!Schema::hasTable('accounting_customer_allocations')) {
            Schema::create('accounting_customer_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('receipt_id')->constrained('accounting_customer_receipts')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('accounting_invoices')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();
                $table->unique(['receipt_id', 'invoice_id']);
            });
        }

        if (!Schema::hasTable('accounting_supplier_invoice_lines')) {
            Schema::create('accounting_supplier_invoice_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('supplier_invoice_id')->constrained('accounting_supplier_invoices')->cascadeOnDelete();
                $table->foreignId('expense_account_id')->constrained('accounting_accounts')->restrictOnDelete();
                $table->string('description');
                $table->decimal('quantity', 14, 4)->default(1);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounting_payment_runs')) {
            Schema::create('accounting_payment_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('run_number', 60);
                $table->date('payment_date');
                $table->string('currency', 3);
                $table->string('payment_method')->default('eft');
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
                $table->timestamps();
                $table->unique(['company_id', 'run_number']);
            });
        }

        if (!Schema::hasTable('accounting_supplier_payments')) {
            Schema::create('accounting_supplier_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('payment_run_id')->nullable()->constrained('accounting_payment_runs')->nullOnDelete();
                $table->foreignId('supplier_id')->constrained('accounting_suppliers')->restrictOnDelete();
                $table->foreignId('supplier_invoice_id')->nullable()->constrained('accounting_supplier_invoices')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
                $table->string('payment_number', 60);
                $table->date('payment_date');
                $table->string('currency', 3);
                $table->decimal('amount', 14, 2);
                $table->string('payment_method')->default('eft');
                $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
                $table->timestamps();
                $table->unique(['company_id', 'payment_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_supplier_payments');
        Schema::dropIfExists('accounting_payment_runs');
        Schema::dropIfExists('accounting_supplier_invoice_lines');
        Schema::dropIfExists('accounting_customer_allocations');
        Schema::dropIfExists('accounting_customer_receipts');
        Schema::dropIfExists('accounting_credit_notes');
        Schema::dropIfExists('accounting_invoice_lines');
    }
};

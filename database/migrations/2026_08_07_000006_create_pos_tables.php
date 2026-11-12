<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounting_pos_sales')) {
            Schema::create('accounting_pos_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('accounting_customers')->nullOnDelete();
                $table->foreignId('warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
                $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('receipt_number', 60);
                $table->timestamp('sold_at');
                $table->string('currency', 3)->default('USD');
                $table->string('payment_method', 30)->default('cash');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('discount_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('amount_tendered', 14, 2)->default(0);
                $table->decimal('change_due', 14, 2)->default(0);
                $table->enum('status', ['posted', 'void'])->default('posted');
                $table->timestamps();
                $table->unique(['company_id', 'receipt_number'], 'acct_pos_receipt_unique');
            });
        }

        if (!Schema::hasTable('accounting_pos_sale_lines')) {
            Schema::create('accounting_pos_sale_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('pos_sale_id')->constrained('accounting_pos_sales')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
                $table->string('description');
                $table->decimal('quantity', 14, 4);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('discount', 14, 2)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('line_total', 14, 2);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('cost_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_pos_sale_lines');
        Schema::dropIfExists('accounting_pos_sales');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounting_sales_orders')) {
            Schema::create('accounting_sales_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('accounting_customers')->restrictOnDelete();
                $table->string('order_number', 60);
                $table->date('order_date');
                $table->date('required_date')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->enum('status', ['draft', 'confirmed', 'fulfilled', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'order_number']);
            });
        }

        if (!Schema::hasTable('accounting_sales_order_lines')) {
            Schema::create('accounting_sales_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('sales_order_id')->constrained('accounting_sales_orders')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
                $table->foreignId('warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
                $table->text('description')->nullable();
                $table->decimal('quantity', 14, 4);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounting_purchase_orders')) {
            Schema::create('accounting_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('supplier_id')->constrained('accounting_suppliers')->restrictOnDelete();
                $table->string('order_number', 60);
                $table->date('order_date');
                $table->date('expected_date')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->enum('status', ['draft', 'approved', 'received', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'order_number']);
            });
        }

        if (!Schema::hasTable('accounting_purchase_order_lines')) {
            Schema::create('accounting_purchase_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('purchase_order_id')->constrained('accounting_purchase_orders')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
                $table->foreignId('warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
                $table->text('description')->nullable();
                $table->decimal('quantity', 14, 4);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounting_transfer_orders')) {
            Schema::create('accounting_transfer_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('transfer_number', 60);
                $table->foreignId('from_warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
                $table->foreignId('to_warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
                $table->date('transfer_date');
                $table->enum('status', ['draft', 'in_transit', 'received', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'transfer_number']);
            });
        }

        if (!Schema::hasTable('accounting_transfer_order_lines')) {
            Schema::create('accounting_transfer_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('transfer_order_id')->constrained('accounting_transfer_orders')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
                $table->decimal('quantity', 14, 4);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounting_stores_requisitions')) {
            Schema::create('accounting_stores_requisitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('requisition_number', 60);
                $table->foreignId('warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('department')->nullable();
                $table->date('required_date')->nullable();
                $table->enum('status', ['draft', 'approved', 'issued', 'cancelled'])->default('draft');
                $table->text('purpose')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'requisition_number'], 'acct_store_req_unique');
            });
        }

        if (!Schema::hasTable('accounting_stores_requisition_lines')) {
            Schema::create('accounting_stores_requisition_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('stores_requisition_id');
                $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
                $table->text('description')->nullable();
                $table->decimal('quantity_requested', 14, 4);
                $table->decimal('quantity_issued', 14, 4)->default(0);
                $table->timestamps();
                $table->foreign('stores_requisition_id', 'acct_store_req_line_req_fk')
                    ->references('id')
                    ->on('accounting_stores_requisitions')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_stores_requisition_lines');
        Schema::dropIfExists('accounting_stores_requisitions');
        Schema::dropIfExists('accounting_transfer_order_lines');
        Schema::dropIfExists('accounting_transfer_orders');
        Schema::dropIfExists('accounting_purchase_order_lines');
        Schema::dropIfExists('accounting_purchase_orders');
        Schema::dropIfExists('accounting_sales_order_lines');
        Schema::dropIfExists('accounting_sales_orders');
    }
};

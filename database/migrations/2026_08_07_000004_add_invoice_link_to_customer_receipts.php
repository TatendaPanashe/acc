<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_customer_receipts') && !Schema::hasColumn('accounting_customer_receipts', 'invoice_id')) {
            Schema::table('accounting_customer_receipts', function (Blueprint $table) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('accounting_invoices')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounting_customer_receipts') && Schema::hasColumn('accounting_customer_receipts', 'invoice_id')) {
            Schema::table('accounting_customer_receipts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('invoice_id');
            });
        }
    }
};

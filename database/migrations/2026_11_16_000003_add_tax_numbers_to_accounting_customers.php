<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Buyer TIN and VAT number: shown on tax invoices and reported to ZIMRA FDMS for business customers.
    public function up(): void
    {
        Schema::table('accounting_customers', function (Blueprint $table) {
            $table->string('tin', 20)->nullable()->after('billing_address');
            $table->string('vat_number', 20)->nullable()->after('tin');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_customers', function (Blueprint $table) {
            $table->dropColumn(['tin', 'vat_number']);
        });
    }
};

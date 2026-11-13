<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The NSSA ceiling is set in USD and converted for ZiG payrolls at the pay-date rate. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_statutory_settings', function (Blueprint $table) {
            $table->string('nssa_ceiling_currency', 3)->nullable()->after('nssa_ceiling'); // null = the setting's currency
        });
    }

    public function down(): void
    {
        Schema::table('payroll_statutory_settings', fn (Blueprint $table) => $table->dropColumn('nssa_ceiling_currency'));
    }
};

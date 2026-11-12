<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * debit/credit stay in the company's base currency (what every report sums).
     * fx_debit/fx_credit hold the amount in the entry's transaction currency.
     */
    public function up(): void
    {
        Schema::table('accounting_journal_lines', function (Blueprint $table) {
            $table->decimal('fx_debit', 18, 2)->nullable()->after('credit');
            $table->decimal('fx_credit', 18, 2)->nullable()->after('fx_debit');
        });

        // Existing lines were recorded without conversion: treat them as already in base currency.
        DB::table('accounting_journal_lines')->update([
            'fx_debit' => DB::raw('debit'),
            'fx_credit' => DB::raw('credit'),
        ]);
    }

    public function down(): void
    {
        Schema::table('accounting_journal_lines', function (Blueprint $table) {
            $table->dropColumn(['fx_debit', 'fx_credit']);
        });
    }
};

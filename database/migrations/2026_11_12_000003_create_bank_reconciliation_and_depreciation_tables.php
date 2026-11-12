<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Index names are given explicitly where Laravel's generated name would exceed MySQL's 64-character limit.
 * Each step is guarded so the migration can resume on MySQL after a partial run (MySQL DDL is not transactional).
 */

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company_bank_accounts', 'gl_account_id')) {
            Schema::table('company_bank_accounts', function (Blueprint $table) {
                $table->foreignId('gl_account_id')->nullable()->after('company_id')->constrained('accounting_accounts')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('accounting_bank_statements', 'company_bank_account_id')) {
            Schema::table('accounting_bank_statements', function (Blueprint $table) {
                $table->foreignId('company_bank_account_id')->nullable()->after('company_id')->constrained('company_bank_accounts')->nullOnDelete();
                $table->decimal('opening_balance', 18, 2)->default(0)->after('statement_date');
                $table->decimal('closing_balance', 18, 2)->default(0)->after('opening_balance');
                $table->timestamp('reconciled_at')->nullable();
                $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        $this->dropLeftoverFromFailedRun('accounting_depreciation_run_lines');
        $this->dropLeftoverFromFailedRun('accounting_depreciation_runs');
        $this->dropLeftoverFromFailedRun('accounting_bank_statement_lines');

        Schema::create('accounting_bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('bank_statement_id')->constrained('accounting_bank_statements')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->decimal('amount', 18, 2); // + money in, - money out (bank account currency)
            $table->foreignId('journal_line_id')->nullable()->unique()->constrained('accounting_journal_lines')->nullOnDelete();
            $table->timestamps();
            $table->index(['bank_statement_id', 'journal_line_id'], 'acc_bank_stmt_lines_stmt_jline_idx');
        });

        if (!Schema::hasColumn('accounting_fixed_assets', 'accumulated_depreciation')) {
            Schema::table('accounting_fixed_assets', function (Blueprint $table) {
                $table->decimal('accumulated_depreciation', 14, 2)->default(0)->after('salvage_value');
                $table->date('depreciated_to')->nullable()->after('accumulated_depreciation');
            });
        }

        Schema::create('accounting_depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->date('run_date');
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'accounting_period_id'], 'acc_depr_runs_company_period_unique');
        });

        Schema::create('accounting_depreciation_run_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('depreciation_run_id')->constrained('accounting_depreciation_runs')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('accounting_fixed_assets')->restrictOnDelete();
            $table->unsignedSmallInteger('months');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    /** A table left half-built by an earlier failed run is empty; anything else is a real problem, so stop. */
    private function dropLeftoverFromFailedRun(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        if (DB::table($table)->exists()) {
            throw new RuntimeException("Table {$table} already exists and contains data; resolve it manually before re-running this migration.");
        }
        Schema::drop($table);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_depreciation_run_lines');
        Schema::dropIfExists('accounting_depreciation_runs');
        Schema::table('accounting_fixed_assets', fn (Blueprint $table) => $table->dropColumn(['accumulated_depreciation', 'depreciated_to']));
        Schema::dropIfExists('accounting_bank_statement_lines');
        Schema::table('accounting_bank_statements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_bank_account_id');
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn(['opening_balance', 'closing_balance', 'reconciled_at']);
        });
        Schema::table('company_bank_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('gl_account_id'));
    }
};

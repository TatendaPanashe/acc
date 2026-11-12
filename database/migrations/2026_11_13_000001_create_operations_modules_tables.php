<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Services\CompanySetup\StandardChartOfAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approvals & audit, reporting, annuity (recurring) billing, BOM / work orders / job costing and AI insights.
 * Index names are kept under MySQL's 64-character limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Older placeholder tables used enums; the working modules need more values, so use strings.
        Schema::table('accounting_subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->change();
            $table->string('status', 20)->default('draft')->change();
        });
        Schema::table('accounting_report_definitions', fn (Blueprint $table) => $table->string('type', 40)->change());
        Schema::table('accounting_job_costs', function (Blueprint $table) {
            $table->string('type', 20)->default('project')->change();
            $table->string('status', 20)->default('planned')->change();
        });
        Schema::table('accounting_ai_forecasts', function (Blueprint $table) {
            $table->string('type', 30)->change();
            $table->string('status', 20)->default('review')->change();
        });

        // ---- Approvals & audit ------------------------------------------------
        Schema::create('accounting_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('document_type', 40); // see ApprovalDocuments::TYPES, or "all"
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->string('approver_role', 30); // finance_manager (or admin) | admin
            $table->boolean('require_different_user')->default(true);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'document_type'], 'acc_appr_rules_company_type_idx');
        });

        Schema::table('accounting_approval_requests', function (Blueprint $table) {
            $table->decimal('amount', 18, 2)->nullable()->after('workflow_key');
            $table->string('reference', 80)->nullable()->after('amount');
            $table->index(['company_id', 'approvable_type', 'approvable_id'], 'acc_appr_req_subject_idx');
        });

        Schema::table('accounting_audit_logs', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable();
            $table->index(['company_id', 'created_at'], 'acc_audit_company_created_idx');
            $table->index(['auditable_type', 'auditable_id'], 'acc_audit_subject_idx');
        });

        // ---- Reporting ----------------------------------------------------------
        Schema::table('accounting_report_definitions', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
        });

        // ---- Annuity / recurring billing -----------------------------------------
        Schema::table('accounting_subscriptions', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('billing_cycle');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->unsignedSmallInteger('term_months')->nullable()->after('ends_on');
            $table->boolean('auto_renew')->default(true)->after('term_months');
            $table->decimal('escalation_percent', 6, 2)->default(0)->after('auto_renew');
            $table->unsignedSmallInteger('payment_terms_days')->default(30)->after('escalation_percent');
            $table->boolean('post_invoices')->default(false)->after('payment_terms_days');
            $table->date('last_invoiced_on')->nullable()->after('next_invoice_on');
            $table->date('cancelled_on')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
        });

        Schema::create('accounting_subscription_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('accounting_subscriptions')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('accounting_items')->nullOnDelete();
            $table->foreignId('revenue_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('accounting_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('accounting_subscriptions')->cascadeOnDelete();
            $table->string('type', 20); // created, invoiced, renewed, escalated, paused, resumed, cancelled, expired
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_billing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('run_number', 40);
            $table->date('run_date');
            $table->unsignedInteger('invoice_count')->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->boolean('posted')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'run_number'], 'acc_billing_runs_company_number_unique');
        });

        // ---- BOM, work orders, job costing ---------------------------------------
        Schema::create('accounting_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete(); // what the BOM makes
            $table->string('code', 40);
            $table->string('name');
            $table->string('version', 20)->default('1');
            $table->decimal('output_quantity', 14, 4)->default(1);
            $table->decimal('labour_hours', 14, 4)->default(0);   // per output batch
            $table->decimal('labour_rate', 18, 2)->default(0);    // per hour
            $table->decimal('overhead_rate', 18, 2)->default(0);  // per labour hour
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'acc_boms_company_code_unique');
        });

        Schema::create('accounting_bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained('accounting_boms')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->decimal('scrap_percent', 6, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('wo_number', 40);
            $table->foreignId('bom_id')->constrained('accounting_boms')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('accounting_warehouses')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('status', 20)->default('draft'); // draft, released, in_progress, completed, cancelled
            $table->date('due_date')->nullable();
            $table->decimal('standard_unit_cost', 18, 4)->default(0);
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('labour_cost', 18, 2)->default(0);
            $table->decimal('overhead_cost', 18, 2)->default(0);
            $table->decimal('labour_hours', 14, 4)->default(0);
            $table->decimal('completed_quantity', 14, 4)->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'wo_number'], 'acc_work_orders_company_number_unique');
        });

        Schema::create('accounting_work_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('accounting_work_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('accounting_items')->restrictOnDelete();
            $table->decimal('required_quantity', 14, 4);
            $table->decimal('issued_quantity', 14, 4)->default(0);
            $table->decimal('issued_cost', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('accounting_job_costs', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('company_id')->constrained('accounting_customers')->nullOnDelete();
            $table->decimal('budget_materials', 18, 2)->default(0);
            $table->decimal('budget_labour', 18, 2)->default(0);
            $table->decimal('budget_overhead', 18, 2)->default(0);
            $table->decimal('budget_other', 18, 2)->default(0);
            $table->decimal('contract_value', 18, 2)->default(0);
            $table->string('billing_method', 20)->default('fixed'); // fixed | cost_plus
            $table->decimal('markup_percent', 6, 2)->default(0);
            $table->decimal('invoiced_amount', 18, 2)->default(0);
            $table->decimal('variance_tolerance', 6, 2)->default(10); // % over budget before review
            $table->date('started_on')->nullable();
            $table->date('closed_on')->nullable();
            $table->foreignId('closing_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
        });

        Schema::create('accounting_job_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('job_cost_id')->constrained('accounting_job_costs')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('category', 20); // materials, labour, overhead, subcontract, other
            $table->string('description');
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('amount', 18, 2);
            $table->foreignId('item_id')->nullable()->constrained('accounting_items')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('accounting_warehouses')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('accounting_invoices', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->constrained('accounting_subscriptions')->nullOnDelete();
            $table->foreignId('billing_run_id')->nullable()->constrained('accounting_billing_runs')->nullOnDelete();
            $table->foreignId('job_cost_id')->nullable()->constrained('accounting_job_costs')->nullOnDelete();
        });

        // ---- AI / ML ------------------------------------------------------------
        Schema::table('accounting_ai_forecasts', function (Blueprint $table) {
            // One row per forecast month now, not one per model. Add the new index first: on MySQL the
            // company foreign key needs an index starting with company_id.
            $table->index(['company_id', 'type', 'forecast_date'], 'acc_ai_forecasts_company_type_date_idx');
            $table->dropUnique(['company_id', 'model_key']);
            $table->decimal('lower_value', 18, 2)->nullable()->after('predicted_value');
            $table->decimal('upper_value', 18, 2)->nullable()->after('lower_value');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::create('accounting_ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('severity', 10); // low, medium, high
            $table->decimal('score', 10, 2)->nullable();
            $table->string('title');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->json('details')->nullable();
            $table->string('fingerprint', 64);
            $table->string('status', 12)->default('open'); // open, dismissed, confirmed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'fingerprint'], 'acc_ai_insights_company_fp_unique');
            $table->index(['company_id', 'status'], 'acc_ai_insights_company_status_idx');
        });

        // New GL accounts used by production & job costing, for companies that already have a chart.
        $chart = app(StandardChartOfAccounts::class);
        foreach (Company::query()->pluck('id') as $companyId) {
            if (Account::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
                $chart->install(Company::find($companyId), ['1250', '6190']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_ai_insights');
        Schema::table('accounting_ai_forecasts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['lower_value', 'upper_value', 'reviewed_at']);
            $table->unique(['company_id', 'model_key']);
            $table->dropIndex('acc_ai_forecasts_company_type_date_idx');
        });
        Schema::table('accounting_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_cost_id');
            $table->dropConstrainedForeignId('billing_run_id');
            $table->dropConstrainedForeignId('subscription_id');
        });
        Schema::dropIfExists('accounting_job_cost_entries');
        Schema::table('accounting_job_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closing_entry_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['budget_materials', 'budget_labour', 'budget_overhead', 'budget_other', 'contract_value', 'billing_method', 'markup_percent', 'invoiced_amount', 'variance_tolerance', 'started_on', 'closed_on']);
        });
        Schema::dropIfExists('accounting_work_order_materials');
        Schema::dropIfExists('accounting_work_orders');
        Schema::dropIfExists('accounting_bom_lines');
        Schema::dropIfExists('accounting_boms');
        Schema::dropIfExists('accounting_billing_runs');
        Schema::dropIfExists('accounting_subscription_events');
        Schema::dropIfExists('accounting_subscription_lines');
        Schema::table('accounting_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on', 'term_months', 'auto_renew', 'escalation_percent', 'payment_terms_days', 'post_invoices', 'last_invoiced_on', 'cancelled_on', 'cancellation_reason', 'notes']);
        });
        Schema::table('accounting_report_definitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['description', 'last_run_at']);
        });
        Schema::table('accounting_audit_logs', function (Blueprint $table) {
            $table->dropIndex('acc_audit_company_created_idx');
            $table->dropIndex('acc_audit_subject_idx');
            $table->dropColumn('checksum');
        });
        Schema::table('accounting_approval_requests', function (Blueprint $table) {
            $table->dropIndex('acc_appr_req_subject_idx');
            $table->dropColumn(['amount', 'reference']);
        });
        Schema::dropIfExists('accounting_approval_rules');
    }
};

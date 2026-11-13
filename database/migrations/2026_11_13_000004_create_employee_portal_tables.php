<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Employee self-service portal: portal credentials, leave types and requests, overtime requests. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employees', function (Blueprint $table) {
            $table->string('portal_password')->nullable();
            $table->timestamp('portal_password_changed_at')->nullable();
            $table->timestamp('portal_last_login_at')->nullable();
        });

        Schema::create('payroll_leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->decimal('days_per_year', 6, 2)->nullable(); // null = no balance tracked
            $table->boolean('paid')->default(true);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'pay_leave_types_company_code_unique');
        });

        Schema::create('payroll_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('payroll_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('payroll_leave_types')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 6, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status'], 'pay_leave_req_company_status_idx');
        });

        Schema::create('payroll_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('payroll_employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 6, 2);
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->foreignId('pay_item_id')->nullable()->constrained('payroll_pay_items')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status'], 'pay_ot_req_company_status_idx');
        });

        Schema::table('payroll_run_inputs', function (Blueprint $table) {
            $table->foreignId('overtime_request_id')->nullable()->constrained('payroll_overtime_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_inputs', fn (Blueprint $table) => $table->dropConstrainedForeignId('overtime_request_id'));
        Schema::dropIfExists('payroll_overtime_requests');
        Schema::dropIfExists('payroll_leave_requests');
        Schema::dropIfExists('payroll_leave_types');
        Schema::table('payroll_employees', fn (Blueprint $table) => $table->dropColumn(['portal_password', 'portal_password_changed_at', 'portal_last_login_at']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_report_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->enum('type', ['financial', 'operational', 'tax', 'management']);
            $table->string('format')->default('pdf');
            $table->json('filters')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('accounting_job_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('job_number', 80);
            $table->string('name');
            $table->enum('type', ['bom', 'work_order', 'project']);
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('actual_cost', 14, 2)->default(0);
            $table->date('due_on')->nullable();
            $table->enum('status', ['planned', 'wip', 'variance_review', 'closed'])->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'job_number']);
        });

        Schema::create('accounting_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('accounting_customers')->nullOnDelete();
            $table->string('contract_number', 80);
            $table->string('description');
            $table->decimal('recurring_amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('ZWL');
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'annual'])->default('monthly');
            $table->date('next_invoice_on')->nullable();
            $table->enum('status', ['draft', 'active', 'renewal_due', 'cancelled'])->default('draft');
            $table->timestamps();
            $table->unique(['company_id', 'contract_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_subscriptions');
        Schema::dropIfExists('accounting_job_costs');
        Schema::dropIfExists('accounting_report_definitions');
    }
};

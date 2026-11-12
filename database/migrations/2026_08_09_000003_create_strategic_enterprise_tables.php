<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_ai_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('model_key', 80);
            $table->enum('type', ['cash_flow', 'revenue', 'inventory', 'expense', 'auto_coding']);
            $table->string('source_module', 80);
            $table->date('forecast_date')->nullable();
            $table->decimal('predicted_value', 14, 2)->default(0);
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('features')->nullable();
            $table->enum('status', ['draft', 'review', 'approved', 'rejected'])->default('draft');
            $table->timestamps();
            $table->unique(['company_id', 'model_key']);
        });

        Schema::create('accounting_portal_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('accounting_customers')->nullOnDelete();
            $table->string('portal_token', 80)->unique();
            $table->string('contact_name');
            $table->string('email');
            $table->json('permissions')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('accounting_revenue_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('accounting_customers')->nullOnDelete();
            $table->string('contract_number', 80);
            $table->string('customer_name')->nullable();
            $table->decimal('transaction_price', 14, 2)->default(0);
            $table->unsignedInteger('performance_obligations')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('recognition_method', ['point_in_time', 'over_time', 'milestone'])->default('over_time');
            $table->enum('status', ['draft', 'active', 'complete', 'cancelled'])->default('draft');
            $table->timestamps();
            $table->unique(['company_id', 'contract_number']);
        });

        Schema::create('accounting_einvoice_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('document_number', 80);
            $table->string('jurisdiction', 3);
            $table->string('network')->default('clearance');
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'cleared', 'rejected', 'cancelled'])->default('draft');
            $table->string('authority_reference')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
        });

        Schema::create('accounting_carbon_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('activity_code', 80);
            $table->enum('scope', ['scope_1', 'scope_2', 'scope_3']);
            $table->string('category');
            $table->decimal('quantity', 14, 4)->default(0);
            $table->string('unit', 30);
            $table->decimal('emission_factor', 14, 6)->default(0);
            $table->decimal('co2e', 14, 4)->default(0);
            $table->date('activity_date');
            $table->string('evidence_reference')->nullable();
            $table->enum('status', ['draft', 'verified', 'reported'])->default('draft');
            $table->timestamps();
            $table->unique(['company_id', 'activity_code']);
        });

        Schema::create('accounting_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('event_type', 120);
            $table->string('source', 80);
            $table->string('external_id', 120)->nullable();
            $table->json('payload');
            $table->enum('status', ['received', 'processed', 'failed'])->default('received');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['event_type', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_webhook_events');
        Schema::dropIfExists('accounting_carbon_activities');
        Schema::dropIfExists('accounting_einvoice_documents');
        Schema::dropIfExists('accounting_revenue_contracts');
        Schema::dropIfExists('accounting_portal_accesses');
        Schema::dropIfExists('accounting_ai_forecasts');
    }
};

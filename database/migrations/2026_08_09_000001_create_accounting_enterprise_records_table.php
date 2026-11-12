<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_enterprise_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('module_key', 80);
            $table->string('code', 80);
            $table->string('title');
            $table->string('owner')->nullable();
            $table->date('record_date')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('status', 60)->default('draft');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'module_key', 'code'], 'acct_enterprise_record_unique');
            $table->index(['company_id', 'module_key', 'status'], 'acct_enterprise_record_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_enterprise_records');
    }
};

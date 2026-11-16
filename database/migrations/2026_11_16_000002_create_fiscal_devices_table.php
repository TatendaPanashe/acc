<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One ZIMRA FDMS fiscal device per company, configured by a platform superuser.
        // The device certificate and private key live in the profile directory on disk (storage/app/private/zimra/{company}).
        Schema::create('fiscal_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 16)->default('test');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('serial_number', 64)->nullable();
            $table->string('model_name', 64)->default('Server');
            $table->string('model_version', 32)->default('v1');
            $table->text('activation_key')->nullable(); // encrypted

            // Tax mapping and receipt options
            $table->unsignedInteger('standard_tax_id')->nullable();
            $table->unsignedInteger('zero_rated_tax_id')->nullable();
            $table->unsignedInteger('exempt_tax_id')->nullable();
            $table->string('zero_rate_lines_as', 16)->default('zero_rated'); // zero_rated | exempt
            $table->string('payment_type', 32)->default('Credit');
            $table->boolean('lines_tax_inclusive')->default(false);
            $table->boolean('auto_submit')->default(true);
            $table->boolean('auto_close_day')->default(true);

            // What FDMS reports back
            $table->timestamp('registered_at')->nullable();
            $table->string('taxpayer_name')->nullable();
            $table->string('taxpayer_tin', 32)->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->string('branch_name')->nullable();
            $table->timestamp('certificate_valid_till')->nullable();
            $table->json('applicable_taxes')->nullable();
            $table->string('fiscal_day_status', 48)->nullable();
            $table->unsignedInteger('fiscal_day_no')->nullable();
            $table->unsignedBigInteger('last_receipt_global_no')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_devices');
    }
};

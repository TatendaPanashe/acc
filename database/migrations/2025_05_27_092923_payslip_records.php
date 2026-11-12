<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payslip_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->date('pay_date');
            $table->decimal('gross_earnings', 10, 2);
            $table->decimal('total_deductions', 10, 2);
            $table->decimal('net_pay', 10, 2);
            $table->decimal('ytd_gross_earnings', 10, 2)->nullable();
            $table->decimal('ytd_total_deductions', 10, 2)->nullable();
            $table->decimal('ytd_net_pay', 10, 2)->nullable();
            $table->decimal('ytd_tax_paid', 10, 2)->nullable();
            $table->string('pdf_path')->nullable(); // Path to the generated PDF file if stored
            $table->string('currency', 3);
            $table->json('data_snapshot')->nullable(); // Store a full JSON snapshot of payslip data
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

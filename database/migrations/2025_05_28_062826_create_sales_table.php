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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique(); // Auto-generated e.g., SALE-20231027123456-XYZ
            $table->datetime('sale_date');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            $table->decimal('change_amount', 10, 2);
            $table->string('payment_method'); // e.g., 'Cash', 'Card', 'Mobile Money'
            $table->string('status')->default('completed'); // 'completed', 'pending', 'cancelled'
            $table->unsignedBigInteger('user_id')->nullable(); // Employee who made the sale

            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null'); // Links to your 'users' table
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sales');
    }
};

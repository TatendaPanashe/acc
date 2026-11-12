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
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // e.g., 'basic_salary', 'allowance', 'bonus', 'commission'
            $table->decimal('value', 10, 2); // Amount or percentage value
            $table->boolean('is_percentage')->default(false); // True if value is a percentage
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_recurring')->default(true); // True for monthly, false for one-time
            $table->date('effective_date')->nullable();
            $table->date('end_date')->nullable();
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

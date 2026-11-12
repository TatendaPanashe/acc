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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('date_joined')->nullable();
            $table->string('designation')->nullable();
            $table->string('telephone')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->unsignedBigInteger('grade_id')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('tax_id_number')->nullable(); // e.g., SSN, TFN, KRA PIN
            $table->string('currency', 3)->default('USD'); // Default currency for the user
            $table->timestamps();

        });


    }

    /**
     * Reverse the migrations.
     */
   public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropColumn([
                'employee_id',
                'job_title',
                'department',
                'grade_id',
                'bank_name',
                'bank_account_number',
                'tax_id_number',
                'currency',
            ]);
        });
    }
};

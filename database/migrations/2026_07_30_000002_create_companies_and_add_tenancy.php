<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('registration_number')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            $table->boolean('is_superuser')->default(false)->after('role');
        });

        $defaultCompanyId = DB::table('companies')->insertGetId([
            'name' => 'Demo Enterprise',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->update(['company_id' => $defaultCompanyId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('is_superuser');
        });

        Schema::dropIfExists('companies');
    }
};

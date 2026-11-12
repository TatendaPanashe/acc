<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_portal_accesses', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_portal_accesses', function (Blueprint $table) {
            $table->dropColumn(['password', 'last_login_at']);
        });
    }
};

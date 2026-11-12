<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_customers', function (Blueprint $table) {
            $table->string('portal_password')->nullable()->after('email');
            $table->timestamp('portal_password_changed_at')->nullable()->after('portal_password');
            $table->timestamp('portal_last_login_at')->nullable()->after('portal_password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_customers', function (Blueprint $table) {
            $table->dropColumn(['portal_password', 'portal_password_changed_at', 'portal_last_login_at']);
        });
    }
};

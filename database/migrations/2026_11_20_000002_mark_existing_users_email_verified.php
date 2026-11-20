<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sign-ups must now confirm their email before using the app. Accounts that existed before
 * this change are treated as confirmed so nobody already using the system is locked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Nothing to undo: we cannot tell which users were confirmed by this migration.
    }
};

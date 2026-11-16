<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-company on/off switches for the events in config/notifications.php (null = defaults).
        Schema::table('companies', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->after('onboarding_completed_at');
        });

        // One row per email handed to the mailer: an audit of what was sent, and (through dedupe_key)
        // a guard so scheduled reminders are never sent twice.
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index();
            $table->string('event', 64)->index();
            $table->string('recipient');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('notification_settings');
        });
    }
};

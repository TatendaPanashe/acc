<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform (SaaS) billing: plans, one subscription per tenant company, an MRR event ledger
 * and the platform's own invoices to tenants. These are platform-level tables (no tenant scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('address');
        });

        Schema::create('platform_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('monthly_price', 10, 2);
            $table->decimal('annual_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->unsignedInteger('max_users')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('platform_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('platform_plan_id')->constrained('platform_plans')->restrictOnDelete();
            $table->string('status', 20)->index(); // trialing, active, past_due, suspended, cancelled
            $table->string('billing_cycle', 10)->default('monthly');
            $table->decimal('price', 10, 2)->default(0); // per billing cycle
            $table->string('currency', 3)->default('USD');
            $table->string('payment_provider', 40)->nullable();
            $table->boolean('is_promotional')->default(false);
            $table->timestamp('promo_ends_at')->nullable();
            $table->string('promo_note')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->string('status_before_suspension', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('platform_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('platform_subscription_id')->constrained('platform_subscriptions')->cascadeOnDelete();
            $table->string('type', 30);       // trial_started, trial_extended, activated, plan_changed, promo_granted, suspended, reactivated, cancelled
            $table->string('movement', 20)->nullable(); // new, expansion, contraction, churn, reactivation
            $table->decimal('mrr_before', 12, 2)->default(0);
            $table->decimal('mrr_after', 12, 2)->default(0);
            $table->decimal('mrr_delta', 12, 2)->default(0);
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('platform_subscription_id')->nullable()->constrained('platform_subscriptions')->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20)->index(); // paid, failed, pending, refunded, void
            $table->string('provider', 40)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('issued_at')->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('platform_plans')->insert([
            ['code' => 'starter', 'name' => 'Starter', 'description' => 'Ledger, invoicing and receipts for small businesses', 'monthly_price' => 29, 'annual_price' => 290, 'max_users' => 3, 'sort_order' => 1, 'currency' => 'USD', 'trial_days' => 14, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'growth', 'name' => 'Growth', 'description' => 'Adds inventory, POS and multi-currency', 'monthly_price' => 79, 'annual_price' => 790, 'max_users' => 10, 'sort_order' => 2, 'currency' => 'USD', 'trial_days' => 14, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'pro', 'name' => 'Pro', 'description' => 'Bank reconciliation, fixed assets and approvals', 'monthly_price' => 149, 'annual_price' => 1490, 'max_users' => 25, 'sort_order' => 3, 'currency' => 'USD', 'trial_days' => 14, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'description' => 'Unlimited users and priority support', 'monthly_price' => 399, 'annual_price' => 3990, 'max_users' => null, 'sort_order' => 4, 'currency' => 'USD', 'trial_days' => 30, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Existing tenants start on a 14-day Starter trial so every company has a subscription.
        $starter = DB::table('platform_plans')->where('code', 'starter')->value('id');
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $subscriptionId = DB::table('platform_subscriptions')->insertGetId([
                'company_id' => $companyId, 'platform_plan_id' => $starter, 'status' => 'trialing',
                'billing_cycle' => 'monthly', 'price' => 29, 'currency' => 'USD',
                'trial_ends_at' => $now->copy()->addDays(14), 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('platform_subscription_events')->insert([
                'company_id' => $companyId, 'platform_subscription_id' => $subscriptionId, 'type' => 'trial_started',
                'details' => json_encode(['plan' => 'starter', 'note' => 'Assigned when platform billing was introduced']),
                'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('platform_subscription_events');
        Schema::dropIfExists('platform_subscriptions');
        Schema::dropIfExists('platform_plans');
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('country'));
    }
};

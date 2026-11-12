<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Subscription billing: plan features & limits, subscription items, payments, gateways,
 * webhooks, usage records and coupons. Tenants are the existing `companies` (now with a slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });
        foreach (DB::table('companies')->get(['id', 'name']) as $company) {
            DB::table('companies')->where('id', $company->id)->update(['slug' => Str::slug($company->name).'-'.$company->id]);
        }

        Schema::table('platform_plans', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('description');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->boolean('is_custom')->default(false)->after('is_featured'); // priced per customer ("From $199")
        });

        Schema::create('platform_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_plan_id')->constrained('platform_plans')->cascadeOnDelete();
            $table->string('feature_key', 60);
            $table->unsignedInteger('limit_value')->nullable(); // limits only; null = unlimited / not a limit
            $table->timestamps();
            $table->unique(['platform_plan_id', 'feature_key']);
        });

        Schema::create('platform_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('discount_type', 10); // percent | fixed
            $table->decimal('discount_value', 10, 2);
            $table->string('duration', 10)->default('once'); // once | forever
            $table->json('applies_to_plans')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('times_redeemed')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('platform_subscriptions', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('trial_ends_at');          // start of the current paid period
            $table->timestamp('grace_ends_at')->nullable()->after('current_period_ends_at');
            $table->string('suspension_type', 10)->nullable()->after('suspension_reason'); // billing | admin
            $table->foreignId('coupon_id')->nullable()->after('promo_note')->constrained('platform_coupons')->nullOnDelete();
            $table->json('limit_overrides')->nullable()->after('coupon_id'); // custom (Enterprise) limits
        });

        Schema::create('platform_subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_subscription_id')->constrained('platform_subscriptions')->cascadeOnDelete();
            $table->string('item_type', 30); // plan, extra_users, extra_branches, custom
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();
        });

        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->string('purpose', 20)->default('renewal')->after('number'); // activation | renewal
            $table->foreignId('platform_plan_id')->nullable()->after('platform_subscription_id')->constrained('platform_plans')->nullOnDelete();
            $table->string('billing_cycle', 10)->nullable()->after('platform_plan_id');
            $table->decimal('subtotal', 10, 2)->default(0)->after('billing_cycle');
            $table->decimal('discount_total', 10, 2)->default(0)->after('subtotal');
            $table->foreignId('coupon_id')->nullable()->after('discount_total')->constrained('platform_coupons')->nullOnDelete();
            $table->json('lines')->nullable()->after('coupon_id');
            $table->timestamp('due_at')->nullable()->after('issued_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('due_at');
        });
        DB::table('platform_invoices')->update(['subtotal' => DB::raw('amount')]);

        Schema::create('platform_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_invoice_id')->constrained('platform_invoices')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('transaction_reference', 80)->unique(); // our reference sent to the provider
            $table->string('provider_reference')->nullable();        // the provider's id for the payment
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20)->index(); // pending, succeeded, failed, cancelled
            $table->string('failure_reason')->nullable();
            $table->string('redirect_url', 1000)->nullable();
            $table->string('poll_url', 1000)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('platform_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->unique();
            $table->string('display_name');
            $table->text('configuration')->nullable(); // encrypted JSON
            $table->string('mode', 10)->default('test'); // test | live
            $table->boolean('status')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('platform_payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_id', 191);
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('platform_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('metric', 30);
            $table->unsignedInteger('quantity');
            $table->string('period', 7); // YYYY-MM
            $table->timestamps();
            $table->unique(['company_id', 'metric', 'period']);
        });

        $this->applyPricing();

        $now = now();
        DB::table('platform_payment_gateways')->insert([
            ['provider' => 'paynow', 'display_name' => 'Paynow (EcoCash, OneMoney, ZimSwitch, Visa)', 'mode' => 'test', 'status' => false, 'sort_order' => 1, 'configuration' => null, 'created_at' => $now, 'updated_at' => $now],
            ['provider' => 'stripe', 'display_name' => 'Card (Stripe)', 'mode' => 'test', 'status' => false, 'sort_order' => 2, 'configuration' => null, 'created_at' => $now, 'updated_at' => $now],
            ['provider' => 'bank_transfer', 'display_name' => 'Bank transfer', 'mode' => 'live', 'status' => true, 'sort_order' => 3, 'configuration' => null, 'created_at' => $now, 'updated_at' => $now],
            ['provider' => 'sandbox', 'display_name' => 'Test payment (sandbox)', 'mode' => 'test', 'status' => !app()->isProduction(), 'sort_order' => 9, 'configuration' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /** Essential $19, Professional $39, Business $79, Enterprise from $199. Annual = 10 × monthly. */
    private function applyPricing(): void
    {
        $plans = [
            // old code => [code, name, tagline, description, monthly, annual, featured, custom, limits, features]
            'starter' => ['essential', 'Essential', 'Entry-level', 'For small businesses.', 19, 190, false, false,
                ['users' => 2, 'branches' => 1, 'companies' => 1],
                ['general_ledger', 'invoicing', 'receivables_payables', 'basic_reports', 'inventory']],
            'growth' => ['professional', 'Professional', 'Most popular', 'For growing businesses.', 39, 390, true, false,
                ['users' => 5, 'branches' => 3, 'companies' => 1],
                ['general_ledger', 'invoicing', 'receivables_payables', 'basic_reports', 'inventory', 'payroll', 'bank_reconciliation', 'multi_currency', 'tax_management', 'advanced_reports']],
            'pro' => ['business', 'Business', null, 'For established businesses.', 79, 790, false, false,
                ['users' => 15, 'branches' => 10, 'companies' => 3],
                ['general_ledger', 'invoicing', 'receivables_payables', 'basic_reports', 'inventory', 'payroll', 'bank_reconciliation', 'multi_currency', 'tax_management', 'advanced_reports', 'advanced_permissions', 'consolidated_reporting', 'audit_trail', 'priority_support']],
            'enterprise' => ['enterprise', 'Enterprise', null, 'For larger organisations.', 199, 1990, false, true,
                ['users' => null, 'branches' => null, 'companies' => null],
                ['general_ledger', 'invoicing', 'receivables_payables', 'basic_reports', 'inventory', 'payroll', 'bank_reconciliation', 'multi_currency', 'tax_management', 'advanced_reports', 'advanced_permissions', 'consolidated_reporting', 'audit_trail', 'priority_support', 'api_access', 'custom_integrations', 'advanced_access_controls', 'dedicated_onboarding', 'dedicated_support']],
        ];

        $now = now();
        foreach ($plans as $oldCode => [$code, $name, $tagline, $description, $monthly, $annual, $featured, $custom, $limits, $features]) {
            $id = DB::table('platform_plans')->where('code', $oldCode)->value('id')
                ?? DB::table('platform_plans')->insertGetId(['code' => $code, 'name' => $name, 'monthly_price' => $monthly, 'annual_price' => $annual, 'created_at' => $now, 'updated_at' => $now]);

            DB::table('platform_plans')->where('id', $id)->update([
                'code' => $code, 'name' => $name, 'tagline' => $tagline, 'description' => $description,
                'monthly_price' => $monthly, 'annual_price' => $annual, 'trial_days' => 14, 'max_users' => $limits['users'],
                'is_featured' => $featured, 'is_custom' => $custom, 'is_active' => true, 'updated_at' => $now,
            ]);

            foreach ($limits as $key => $value) {
                DB::table('platform_plan_features')->insert(['platform_plan_id' => $id, 'feature_key' => $key, 'limit_value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ($features as $key) {
                DB::table('platform_plan_features')->insert(['platform_plan_id' => $id, 'feature_key' => $key, 'limit_value' => null, 'created_at' => $now, 'updated_at' => $now]);
            }

            // Existing trials and paid subscriptions follow the new price of their plan.
            DB::table('platform_subscriptions')->where('platform_plan_id', $id)->where('is_promotional', false)
                ->update(['price' => DB::raw("CASE WHEN billing_cycle = 'annual' THEN {$annual} ELSE {$monthly} END")]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_usage_records');
        Schema::dropIfExists('platform_payment_webhooks');
        Schema::dropIfExists('platform_payment_gateways');
        Schema::dropIfExists('platform_payments');
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_plan_id');
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['purpose', 'billing_cycle', 'subtotal', 'discount_total', 'lines', 'due_at', 'reminder_sent_at']);
        });
        Schema::dropIfExists('platform_subscription_items');
        Schema::table('platform_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['starts_at', 'grace_ends_at', 'suspension_type', 'limit_overrides']);
        });
        Schema::dropIfExists('platform_coupons');
        Schema::dropIfExists('platform_plan_features');
        Schema::table('platform_plans', fn (Blueprint $table) => $table->dropColumn(['tagline', 'is_featured', 'is_custom']));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('slug'));
    }
};

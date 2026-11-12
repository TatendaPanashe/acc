<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\Coupon;
use App\Models\Platform\PaymentGateway;
use App\Models\Platform\PaymentWebhook;
use App\Models\Platform\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Platform administrator: plans & features, coupons and payment gateways. */
class PlatformBillingController extends Controller
{
    public const GATEWAY_FIELDS = [
        'pesepay' => ['integration_key' => 'Integration key|secret', 'encryption_key' => 'Encryption key (16, 24 or 32 characters)|secret'],
        'paynow' => ['integration_id' => 'Integration ID', 'integration_key' => 'Integration key|secret', 'auth_email' => 'Merchant email (test mode)'],
        'stripe' => ['secret_key' => 'Secret key|secret', 'webhook_secret' => 'Webhook signing secret|secret'],
        'bank_transfer' => ['bank' => 'Bank', 'account_name' => 'Account name', 'account_number' => 'Account number', 'branch' => 'Branch'],
        'sandbox' => [],
    ];

    public function plans(): View
    {
        return view('platform.billing.plans', [
            'plans' => Plan::with('features')->orderBy('sort_order')->get(),
            'features' => collect(config('billing.features')),
        ]);
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $limits = ['users', 'branches', 'companies'];
        $toggles = collect(config('billing.features'))->filter(fn ($f) => $f[1] === 'feature')->keys()->all();
        $data = $request->validate([
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'limits.*' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in($toggles)],
        ]);

        DB::transaction(function () use ($plan, $data, $limits, $request) {
            $plan->update(['monthly_price' => $data['monthly_price'], 'annual_price' => $data['annual_price'], 'is_active' => $request->boolean('is_active'), 'max_users' => $data['limits']['users'] ?? null]);
            $plan->features()->delete();
            foreach ($limits as $metric) {
                $plan->features()->create(['feature_key' => $metric, 'limit_value' => $data['limits'][$metric] ?? null]);
            }
            foreach ($data['features'] ?? [] as $key) {
                $plan->features()->create(['feature_key' => $key]);
            }
        });

        return back()->with('success', $plan->name.' updated. Existing subscribers keep their current price until their plan is changed.');
    }

    public function coupons(): View
    {
        return view('platform.billing.coupons', [
            'coupons' => Coupon::latest()->get(),
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('platform_coupons', 'code')],
            'description' => ['nullable', 'max:255'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'gt:0', $request->input('discount_type') === 'percent' ? 'max:100' : 'max:100000'],
            'duration' => ['required', Rule::in(['once', 'forever'])],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'applies_to_plans' => ['nullable', 'array'],
            'applies_to_plans.*' => [Rule::exists('platform_plans', 'code')],
        ]);
        $data['code'] = Str::upper($data['code']);
        $data['applies_to_plans'] = $data['applies_to_plans'] ?? null;
        Coupon::create($data + ['is_active' => true]);

        return back()->with('success', 'Coupon '.$data['code'].' created.');
    }

    public function toggleCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => !$coupon->is_active]);

        return back()->with('success', 'Coupon '.$coupon->code.' '.($coupon->is_active ? 'activated' : 'deactivated').'.');
    }

    public function gateways(): View
    {
        return view('platform.billing.gateways', [
            'gateways' => PaymentGateway::orderBy('sort_order')->get(),
            'fields' => self::GATEWAY_FIELDS,
            'webhooks' => PaymentWebhook::latest()->take(15)->get(),
        ]);
    }

    public function updateGateway(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $fields = self::GATEWAY_FIELDS[$gateway->provider] ?? [];
        $data = $request->validate([
            'display_name' => ['required', 'max:255'],
            'mode' => ['required', Rule::in(['test', 'live'])],
            'status' => ['nullable', 'boolean'],
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable', 'string', 'max:500'],
        ]);

        if ($gateway->provider === 'sandbox' && app()->isProduction() && $request->boolean('status')) {
            return back()->with('error', 'The sandbox gateway cannot be enabled in production.');
        }

        $encryptionKey = trim((string) ($data['config']['encryption_key'] ?? ''));
        if ($gateway->provider === 'pesepay' && $encryptionKey !== '' && !in_array(strlen($encryptionKey), [16, 24, 32], true)) {
            return back()->withErrors(['config.encryption_key' => 'The Pesepay encryption key must be 16, 24 or 32 characters. Copy it exactly from the Pesepay dashboard.'])->withInput();
        }

        // Blank secret fields keep the stored value.
        $config = $gateway->configuration ?? [];
        foreach ($fields as $key => $label) {
            $value = trim((string) ($data['config'][$key] ?? ''));
            if ($value !== '' || !str_ends_with($label, '|secret')) {
                $config[$key] = $value;
            }
        }

        $gateway->update(['display_name' => $data['display_name'], 'mode' => $data['mode'], 'status' => $request->boolean('status'), 'configuration' => $config]);

        return back()->with('success', $gateway->display_name.' saved.');
    }
}

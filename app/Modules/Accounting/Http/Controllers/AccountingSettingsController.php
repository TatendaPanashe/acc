<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\ExchangeRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Modules\Accounting\Models\TaxCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingSettingsController extends Controller
{
    public function index(Request $request, ExchangeRateService $rates): View
    {
        $companyId = (int) $request->user()->company_id;

        return view('accounting.settings.index', [
            'currencies' => Currency::orderByDesc('is_base')->orderBy('code')->paginate(10, ['*'], 'currencies'),
            'taxCodes' => TaxCode::orderBy('code')->paginate(10, ['*'], 'tax_codes'),
            'accounts' => Account::orderBy('code')->pluck('name', 'id')->prepend('No control account', ''),
            'baseCurrency' => $rates->baseCurrency($companyId),
            'foreignCurrencies' => Currency::where('status', true)->where('is_base', false)->orderBy('code')->pluck('code'),
            'exchangeRates' => ExchangeRate::orderByDesc('rate_date')->orderBy('from_currency')->paginate(15, ['*'], 'rates'),
            'mappingGroups' => config('accounting.account_mappings'),
            'mapped' => AccountMapping::pluck('account_id', 'key'),
            'postableAccounts' => Account::where('allow_posting', true)->where('status', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function updateMappings(Request $request, AccountMappingService $mappings): RedirectResponse
    {
        $keys = array_keys(AccountMappingService::definitions());
        $data = $request->validate([
            'mappings' => ['array'],
            'mappings.*' => ['nullable', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
        ]);

        DB::transaction(function () use ($data, $keys) {
            foreach ($keys as $key) {
                $accountId = $data['mappings'][$key] ?? null;
                $accountId
                    ? AccountMapping::updateOrCreate(['key' => $key], ['account_id' => $accountId])
                    : AccountMapping::where('key', $key)->delete();
            }
        });
        $mappings->forget();

        return redirect()->route('accounting.settings.index')->with('success', 'Account mapping saved.');
    }

    public function storeRate(Request $request, ExchangeRateService $rates): RedirectResponse
    {
        $base = $rates->baseCurrency((int) $request->user()->company_id);
        $data = $request->validate([
            'from_currency' => ['required', 'size:3', 'not_in:'.$base, TenantExists::currency()],
            'rate_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'source' => ['nullable', 'max:255'],
        ]);

        ExchangeRate::updateOrCreate(
            ['from_currency' => strtoupper($data['from_currency']), 'to_currency' => $base, 'rate_date' => $data['rate_date']],
            ['rate' => $data['rate'], 'source' => $data['source'] ?? 'Manual'],
        );

        return back()->with('success', 'Exchange rate saved: 1 '.strtoupper($data['from_currency']).' = '.$data['rate'].' '.$base.'.');
    }

    public function destroyRate(ExchangeRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('success', 'Exchange rate deleted. Posted entries keep the rate they were posted with.');
    }

    public function storeCurrency(Request $request): RedirectResponse
    {
        $data = $this->currencyData($request);
        if (!$data['is_base'] && Currency::where('is_base', true)->exists()) {
            app(\App\Services\Billing\Entitlements::class)->assertFeature($request->user()->company, 'multi_currency', 'code');
        }
        Currency::create($data);

        return back()->with('success', 'Currency created.');
    }

    public function updateCurrency(Request $request, Currency $currency): RedirectResponse
    {
        $currency->update($this->currencyData($request, $currency));

        return redirect()->route('accounting.settings.index')->with('success', 'Currency updated.');
    }

    public function destroyCurrency(Currency $currency): RedirectResponse
    {
        $currency->update(['status' => false]);

        return back()->with('success', 'Currency deactivated.');
    }

    public function storeTaxCode(Request $request): RedirectResponse
    {
        TaxCode::create($this->taxData($request));

        return back()->with('success', 'Tax code created.');
    }

    public function updateTaxCode(Request $request, TaxCode $taxCode): RedirectResponse
    {
        $taxCode->update($this->taxData($request, $taxCode));

        return redirect()->route('accounting.settings.index')->with('success', 'Tax code updated.');
    }

    public function destroyTaxCode(TaxCode $taxCode): RedirectResponse
    {
        $taxCode->update(['status' => false]);

        return back()->with('success', 'Tax code deactivated.');
    }

    private function currencyData(Request $request, ?Currency $currency = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'size:3', Rule::unique('accounting_currencies')->where('company_id', $request->user()->company_id)->ignore($currency)],
            'name' => ['required', 'max:255'],
            'symbol' => ['nullable', 'max:8'],
            'is_base' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_base'] = $request->boolean('is_base');
        $data['status'] = $request->boolean('status', true);

        $currentBase = Currency::where('is_base', true)->value('code');
        $changesBase = ($data['is_base'] && $currentBase && $currentBase !== $data['code'])
            || ($currency?->is_base && !$data['is_base']);

        if ($changesBase && JournalEntry::where('status', 'posted')->exists()) {
            throw ValidationException::withMessages(['is_base' => 'The base currency cannot be changed after entries have been posted.']);
        }

        if ($data['is_base']) {
            Currency::when($currency, fn ($q) => $q->whereKeyNot($currency->id))->update(['is_base' => false]);
        }

        return $data;
    }

    private function taxData(Request $request, ?TaxCode $taxCode = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'max:30', Rule::unique('accounting_tax_codes')->where('company_id', $request->user()->company_id)->ignore($taxCode)],
            'name' => ['required', 'max:255'],
            'type' => ['required', Rule::in(['sales', 'purchase', 'both'])],
            'rate' => ['required', 'numeric', 'min:0'],
            'control_account_id' => ['nullable', new TenantExists('accounting_accounts')],
            'status' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['status'] = $request->boolean('status', true);

        return $data;
    }
}

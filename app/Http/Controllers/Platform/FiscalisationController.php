<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Platform\FiscalDevice;
use App\Modules\Accounting\Models\EinvoiceDocument;
use App\Modules\Accounting\Services\ZimraFdmsService;
use App\Services\Fiscal\FdmsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** System → Fiscalisation: per-company ZIMRA FDMS device set-up (superusers only). */
class FiscalisationController extends Controller
{
    public function __construct(private readonly ZimraFdmsService $fdms)
    {
    }

    public function index(Request $request): View
    {
        $companies = Company::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('name')->paginate(25)->withQueryString();
        $devices = FiscalDevice::whereIn('company_id', $companies->pluck('id'))->get()->keyBy('company_id');
        $fiscalised = EinvoiceDocument::withoutGlobalScopes()->where('network', 'ZIMRA FDMS')->whereNotNull('authority_reference')
            ->whereIn('company_id', $companies->pluck('id'))->selectRaw('company_id, count(*) as n, max(updated_at) as last_at')
            ->groupBy('company_id')->get()->keyBy('company_id');

        return view('platform.fiscalisation.index', [
            'companies' => $companies,
            'devices' => $devices,
            'fiscalised' => $fiscalised,
            'counts' => [
                'active' => FiscalDevice::where('enabled', true)->whereNotNull('registered_at')->count(),
                'registered' => FiscalDevice::whereNotNull('registered_at')->count(),
                'attention' => FiscalDevice::whereNotNull('last_error')->count(),
            ],
        ]);
    }

    public function edit(Company $company): View
    {
        $device = FiscalDevice::firstOrNew(['company_id' => $company->id]);

        return view('platform.fiscalisation.edit', [
            'company' => $company,
            'device' => $device,
            'recent' => EinvoiceDocument::withoutGlobalScopes()->where('company_id', $company->id)->where('network', 'ZIMRA FDMS')->latest('id')->limit(10)->get(),
            'environments' => config('zimra.environments'),
            'paymentTypes' => config('zimra.payment_types'),
            'profileExists' => $device->exists && is_file($this->fdms->profileDir($device).'/device.json'),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $device = FiscalDevice::firstOrNew(['company_id' => $company->id]);
        $data = $request->validate([
            'environment' => ['required', Rule::in(array_keys(config('zimra.environments')))],
            'device_id' => ['required', 'integer', 'min:1'],
            'serial_number' => ['required', 'string', 'max:64'],
            'model_name' => ['required', 'string', 'max:64'],
            'model_version' => ['required', 'string', 'max:32'],
            'activation_key' => ['nullable', 'string', 'max:64'],
            'standard_tax_id' => ['nullable', 'integer', 'min:1'],
            'zero_rated_tax_id' => ['nullable', 'integer', 'min:1'],
            'exempt_tax_id' => ['nullable', 'integer', 'min:1'],
            'zero_rate_lines_as' => ['required', Rule::in(['zero_rated', 'exempt'])],
            'payment_type' => ['required', Rule::in(config('zimra.payment_types'))],
            'lines_tax_inclusive' => ['nullable', 'boolean'],
            'auto_submit' => ['nullable', 'boolean'],
            'auto_close_day' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        // Changing the device identity or environment means registering again.
        $identityChanged = $device->exists && $device->isRegistered() && (
            (int) $device->device_id !== (int) $data['device_id'] || $device->serial_number !== $data['serial_number'] || $device->environment !== $data['environment']);

        if (blank($data['activation_key'] ?? null)) {
            unset($data['activation_key']); // blank keeps the saved key
        }
        foreach (['lines_tax_inclusive', 'auto_submit', 'auto_close_day', 'enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        if ($data['enabled'] && !$device->isRegistered()) {
            $data['enabled'] = false;
            session()->flash('warning', 'Saved. Register the device with ZIMRA before enabling fiscalisation.');
        }

        $device->fill($data);
        $device->company_id = $company->id;
        if ($identityChanged) {
            $device->forceFill(['registered_at' => null, 'enabled' => false]);
        }
        $device->save();

        return redirect()->route('system.fiscalisation.edit', $company)
            ->with('success', $identityChanged ? 'Saved. The device details changed, so register it with ZIMRA again.' : 'Fiscalisation settings saved.');
    }

    public function register(Company $company): RedirectResponse
    {
        return $this->attempt($company, fn (FiscalDevice $d) => $this->fdms->register($d),
            fn (FiscalDevice $d) => 'Device registered with ZIMRA'.($d->taxpayer_name ? ' for '.$d->taxpayer_name : '').'. Check the tax mapping, then enable fiscalisation.');
    }

    public function refresh(Company $company): RedirectResponse
    {
        return $this->attempt($company, fn (FiscalDevice $d) => $this->fdms->refresh($d), fn () => 'Status refreshed from ZIMRA.');
    }

    public function openDay(Company $company): RedirectResponse
    {
        return $this->attempt($company, fn (FiscalDevice $d) => $this->fdms->openDay($d), fn (FiscalDevice $d) => 'Fiscal day '.$d->fiscal_day_no.' opened.');
    }

    public function closeDay(Company $company): RedirectResponse
    {
        return $this->attempt($company, fn (FiscalDevice $d) => $this->fdms->closeDay($d), fn () => 'Fiscal day closed.');
    }

    private function attempt(Company $company, callable $action, callable $message): RedirectResponse
    {
        $device = FiscalDevice::where('company_id', $company->id)->first();
        if (!$device) {
            return back()->with('error', 'Save the device details first.');
        }
        try {
            $device = $action($device);
        } catch (FdmsException $e) {
            return back()->with('error', 'ZIMRA: '.$e->getMessage());
        }

        return back()->with('success', $message($device));
    }
}

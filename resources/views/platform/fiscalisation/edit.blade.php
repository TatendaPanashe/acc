@extends('layouts.platform')
@section('title', 'Fiscalisation · '.$company->name)
@section('styles')
    .fz-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 20px; align-items: start; }
    .fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 12px 0 18px; }
    .fields .wide { grid-column: 1 / -1; }
    .hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
    .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin: 18px 0 0; }
    .toggle { display: flex; gap: 10px; align-items: flex-start; margin: 10px 0; font-weight: 500; }
    .toggle input { margin-top: 3px; } .toggle small { display: block; color: var(--muted); font-weight: 400; }
    .kv { display: grid; grid-template-columns: max-content 1fr; gap: 8px 16px; margin: 12px 0; font-size: 14px; }
    .kv dt { color: var(--muted); } .kv dd { margin: 0; font-weight: 600; text-align: right; word-break: break-word; }
    .steps { list-style: none; padding: 0; margin: 12px 0 0; display: grid; gap: 10px; }
    .steps li { display: flex; gap: 10px; align-items: center; font-size: 14px; }
    .steps .dot { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; background: var(--surface-2); color: var(--muted); flex: none; }
    .steps .done .dot { background: var(--good); color: #fff; }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .error-box { background: rgba(208, 59, 59, 0.08); color: var(--bad); border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-top: 12px; word-break: break-word; }
    @media (max-width: 1100px) { .fz-grid, .fields { grid-template-columns: minmax(0, 1fr); } }
@endsection
@section('content')
@php
    $taxes = collect($device->applicable_taxes ?? []);
    $taxLabel = fn ($t) => 'ID '.$t['id'].' · '.($t['percent'] === null ? 'exempt' : rtrim(rtrim(number_format((float) $t['percent'], 2), '0'), '.').'%').' · '.$t['name'];
    $registered = $device->exists && $device->isRegistered();
    $steps = [
        ['Enter the device details from the ZIMRA portal', $device->exists && $device->device_id],
        ['Register the device with ZIMRA', $registered],
        ['Check the tax mapping', $registered && $device->standard_tax_id],
        ['Enable fiscalisation', $device->exists && $device->isActive()],
    ];
@endphp
<div class="page-head">
    <div>
        <h1>{{ $company->name }}</h1>
        <p>ZIMRA FDMS fiscal device ·
            @if($device->exists)<span class="badge {{ $device->statusBadge() }}">{{ $device->statusLabel() }}</span>@else<span class="badge cancelled">Not set up</span>@endif
        </p>
    </div>
    <a href="{{ route('system.fiscalisation.index') }}" class="btn btn-ghost"><x-platform.icon name="arrow-left" size="16"/> All businesses</a>
</div>

<div class="fz-grid">
    <form method="POST" action="{{ route('system.fiscalisation.update', $company) }}" class="card">
        @csrf @method('PUT')
        <div class="card-head"><h2>Device settings</h2><span class="sub">From the ZIMRA FDMS taxpayer portal</span></div>

        <p class="section-title">Fiscal device</p>
        <div class="fields">
            <div><label for="environment">Environment</label>
                <select id="environment" name="environment">@foreach($environments as $key => $label)<option value="{{ $key }}" @selected(old('environment', $device->environment ?? 'test') === $key)>{{ $label }}</option>@endforeach</select>
                <div class="hint">Use Test until ZIMRA approves the business for production.</div></div>
            <div><label for="device_id">Device ID</label><input type="number" id="device_id" name="device_id" min="1" value="{{ old('device_id', $device->device_id) }}" required></div>
            <div><label for="serial_number">Device serial number</label><input type="text" id="serial_number" name="serial_number" maxlength="64" value="{{ old('serial_number', $device->serial_number) }}" required></div>
            <div><label for="activation_key">Activation key</label>
                <input type="password" id="activation_key" name="activation_key" autocomplete="new-password" maxlength="64" placeholder="{{ $device->exists && $device->activation_key ? '•••••••• (saved)' : ($registered ? 'Used: device registered' : 'From the ZIMRA portal') }}">
                <div class="hint">Stored encrypted and deleted once the device is registered.</div></div>
            <div><label for="model_name">Device model name</label><input type="text" id="model_name" name="model_name" maxlength="64" value="{{ old('model_name', $device->model_name ?? 'Server') }}" required></div>
            <div><label for="model_version">Device model version</label><input type="text" id="model_version" name="model_version" maxlength="32" value="{{ old('model_version', $device->model_version ?? 'v1') }}" required></div>
        </div>

        <p class="section-title">Tax mapping</p>
        <div class="hint">Which ZIMRA tax each invoice line is reported under. Filled in from ZIMRA's tax table when the device is registered.</div>
        <div class="fields">
            @foreach(['standard_tax_id' => 'Standard-rated lines (VAT > 0%)', 'zero_rated_tax_id' => 'Zero-rated lines (0%)', 'exempt_tax_id' => 'Exempt lines'] as $field => $label)
                <div><label for="{{ $field }}">{{ $label }}</label>
                    @if($taxes->isNotEmpty())
                        <select id="{{ $field }}" name="{{ $field }}"><option value="">Not used</option>@foreach($taxes as $t)<option value="{{ $t['id'] }}" @selected((int) old($field, $device->$field) === $t['id'])>{{ $taxLabel($t) }}</option>@endforeach</select>
                    @else
                        <input type="number" id="{{ $field }}" name="{{ $field }}" min="1" value="{{ old($field, $device->$field) }}" placeholder="ZIMRA tax ID">
                    @endif
                </div>
            @endforeach
            <div><label for="zero_rate_lines_as">Report 0% lines as</label>
                <select id="zero_rate_lines_as" name="zero_rate_lines_as">
                    <option value="zero_rated" @selected(old('zero_rate_lines_as', $device->zero_rate_lines_as ?? 'zero_rated') === 'zero_rated')>Zero-rated</option>
                    <option value="exempt" @selected(old('zero_rate_lines_as', $device->zero_rate_lines_as) === 'exempt')>Exempt</option>
                </select></div>
        </div>

        <p class="section-title">Receipts</p>
        <div class="fields">
            <div><label for="payment_type">Payment type reported for invoices</label>
                <select id="payment_type" name="payment_type">@foreach($paymentTypes as $type)<option value="{{ $type }}" @selected(old('payment_type', $device->payment_type ?? 'Credit') === $type)>{{ $type }}</option>@endforeach</select>
                <div class="hint">Invoices on account are normally “Credit”.</div></div>
        </div>
        <label class="toggle"><input type="checkbox" name="lines_tax_inclusive" value="1" @checked(old('lines_tax_inclusive', $device->lines_tax_inclusive))><span>Invoice line prices include VAT<small>Leave off: acc263 invoice prices are before VAT.</small></span></label>
        <label class="toggle"><input type="checkbox" name="auto_submit" value="1" @checked(old('auto_submit', $device->exists ? $device->auto_submit : true))><span>Fiscalise invoices automatically when they are posted<small>Otherwise staff use “Send to ZIMRA” on each invoice.</small></span></label>
        <label class="toggle"><input type="checkbox" name="auto_close_day" value="1" @checked(old('auto_close_day', $device->exists ? $device->auto_close_day : true))><span>Close the fiscal day automatically every night (23:50)<small>A fiscal day opens by itself with the first invoice of the day.</small></span></label>

        <p class="section-title">Status</p>
        <label class="toggle"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $device->enabled)) @disabled(!$registered)><span>Fiscalisation enabled for {{ $company->name }}<small>{{ $registered ? 'When off, invoices are not sent to ZIMRA.' : 'Register the device first.' }}</small></span></label>

        <div class="actions"><button class="btn btn-primary"><x-platform.icon name="check" size="16"/> Save settings</button></div>
    </form>

    <div class="grid">
        <div class="card">
            <div class="card-head"><h2>Set-up checklist</h2></div>
            <ol class="steps">
                @foreach($steps as $i => [$label, $done])
                    <li class="{{ $done ? 'done' : '' }}"><span class="dot">{{ $done ? '✓' : $i + 1 }}</span>{{ $label }}</li>
                @endforeach
            </ol>
            @if($device->exists && $device->device_id)
                <div class="actions">
                    <form method="POST" action="{{ route('system.fiscalisation.register', $company) }}" onsubmit="return confirm('{{ $registered ? 'Register again? This replaces the device certificate and key.' : 'Register this device with ZIMRA now?' }}')">@csrf
                        <button class="btn {{ $registered ? 'btn-outline' : 'btn-primary' }}"><x-platform.icon name="shield" size="16"/> {{ $registered ? 'Re-register device' : 'Register with ZIMRA' }}</button>
                    </form>
                </div>
                @if(!$registered && !$device->activation_key)<div class="hint">Save the activation key before registering.</div>@endif
            @endif
        </div>

        @if($registered)
            <div class="card">
                <div class="card-head"><h2>ZIMRA status</h2><span class="sub">{{ $device->last_checked_at ? 'checked '.$device->last_checked_at->diffForHumans() : '' }}</span></div>
                <dl class="kv">
                    <dt>Taxpayer</dt><dd>{{ $device->taxpayer_name ?? '—' }}</dd>
                    <dt>TIN</dt><dd>{{ $device->taxpayer_tin ?? '—' }}</dd>
                    <dt>VAT number</dt><dd>{{ $device->vat_number ?? '—' }}</dd>
                    <dt>Branch</dt><dd>{{ $device->branch_name ?? '—' }}</dd>
                    <dt>Environment</dt><dd>{{ ucfirst($device->environment) }}</dd>
                    <dt>Certificate valid until</dt><dd style="{{ $device->certificate_valid_till && $device->certificate_valid_till->lt(now()->addDays(30)) ? 'color: var(--bad)' : '' }}">{{ $device->certificate_valid_till?->format('d M Y') ?? '—' }}</dd>
                    <dt>Fiscal day</dt><dd>{{ $device->fiscal_day_status ? str_replace('FiscalDay', '', $device->fiscal_day_status) : '—' }}{{ $device->fiscal_day_no ? ' · day '.$device->fiscal_day_no : '' }}</dd>
                    <dt>Last receipt (global no)</dt><dd>{{ $device->last_receipt_global_no ?? '—' }}</dd>
                </dl>
                @if($device->last_error)<div class="error-box"><strong>Last error:</strong> {{ $device->last_error }}</div>@endif
                <div class="actions">
                    <form method="POST" action="{{ route('system.fiscalisation.refresh', $company) }}">@csrf<button class="btn btn-outline"><x-platform.icon name="repeat" size="16"/> Refresh</button></form>
                    @if($device->dayIsOpen())
                        <form method="POST" action="{{ route('system.fiscalisation.day.close', $company) }}" onsubmit="return confirm('Close the current fiscal day?')">@csrf<button class="btn btn-outline"><x-platform.icon name="lock" size="16"/> Close fiscal day</button></form>
                    @else
                        <form method="POST" action="{{ route('system.fiscalisation.day.open', $company) }}">@csrf<button class="btn btn-outline"><x-platform.icon name="calendar" size="16"/> Open fiscal day</button></form>
                    @endif
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head"><h2>Recent fiscal invoices</h2></div>
            <div class="table-wrap"><table class="data">
                <thead><tr><th>Invoice</th><th>Status</th><th>Receipt ID</th><th>When</th></tr></thead>
                <tbody>
                @forelse($recent as $doc)
                    <tr><td>{{ $doc->document_number }}</td>
                        <td><span class="badge {{ $doc->status === 'cleared' ? 'paid' : ($doc->status === 'rejected' ? 'failed' : 'pending') }}">{{ ucfirst($doc->status) }}</span></td>
                        <td>{{ $doc->authority_reference ?? '—' }}@if($doc->status === 'rejected' && data_get($doc->payload, 'error'))<div class="hint">{{ \Illuminate\Support\Str::limit(data_get($doc->payload, 'error'), 120) }}</div>@endif</td>
                        <td>{{ $doc->updated_at?->format('d M H:i') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">No invoices sent to ZIMRA yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

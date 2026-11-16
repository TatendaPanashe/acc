@extends('layouts.platform')
@section('title', 'Fiscalisation')
@use('App\Support\PlatformFormat', 'F')
@section('styles')
    .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stat strong { display: block; font-size: 26px; }
    .stat span { color: var(--muted); font-size: 13px; }
    .filters { display: grid; grid-template-columns: minmax(180px, 1fr) auto; gap: 12px; align-items: end; margin-bottom: 20px; }
    .tenant-name { display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; }
    .tenant-name strong { display: block; } .tenant-name span { color: var(--muted); font-size: 13px; }
    @media (max-width: 760px) { .stats, .filters { grid-template-columns: minmax(0, 1fr); } }
@endsection
@section('content')
<div class="page-head">
    <div><h1>Fiscalisation</h1><p>Set up each business's ZIMRA FDMS fiscal device so its invoices are fiscalised and signed by ZIMRA.</p></div>
</div>

<div class="stats">
    <div class="card stat"><strong>{{ $counts['active'] }}</strong><span>Businesses fiscalising</span></div>
    <div class="card stat"><strong>{{ $counts['registered'] }}</strong><span>Devices registered</span></div>
    <div class="card stat"><strong style="{{ $counts['attention'] ? 'color: var(--bad)' : '' }}">{{ $counts['attention'] }}</strong><span>Need attention</span></div>
</div>

<form method="GET" class="card filters" aria-label="Search businesses">
    <div><label for="q">Search</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Business name"></div>
    <div><button class="btn btn-primary"><x-platform.icon name="search" size="16"/> Search</button></div>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Business</th><th>Status</th><th>Environment</th><th>Device</th><th>Fiscal day</th><th class="num">Invoices fiscalised</th><th></th></tr></thead>
            <tbody>
            @forelse($companies as $company)
                @php $device = $devices[$company->id] ?? null; $stats = $fiscalised[$company->id] ?? null; @endphp
                <tr>
                    <td><a class="tenant-name" href="{{ route('system.fiscalisation.edit', $company) }}">
                        <span class="avatar sm">{{ F::initials($company->name) }}</span>
                        <span><strong>{{ $company->name }}</strong><span>{{ $device?->taxpayer_tin ? 'TIN '.$device->taxpayer_tin : ($company->tin ? 'TIN '.$company->tin : 'No TIN') }}</span></span>
                    </a></td>
                    <td>
                        @if($device)
                            <span class="badge {{ $device->statusBadge() }}">{{ $device->statusLabel() }}</span>
                        @else
                            <span class="badge cancelled">Not set up</span>
                        @endif
                    </td>
                    <td>{{ $device ? ucfirst($device->environment) : '—' }}</td>
                    <td>{{ $device?->device_id ? $device->device_id.' · '.$device->serial_number : '—' }}</td>
                    <td>{{ $device?->fiscal_day_status ? str_replace('FiscalDay', '', $device->fiscal_day_status).($device->fiscal_day_no ? ' (day '.$device->fiscal_day_no.')' : '') : '—' }}</td>
                    <td class="num">{{ $stats?->n ?? 0 }}@if($stats?->last_at)<br><span class="muted" style="font-size:12px">last {{ \Illuminate\Support\Carbon::parse($stats->last_at)->diffForHumans() }}</span>@endif</td>
                    <td><a class="btn btn-ghost" href="{{ route('system.fiscalisation.edit', $company) }}">{{ $device ? 'Manage' : 'Set up' }} <x-platform.icon name="chevron-right" size="16"/></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No businesses found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $companies->links() }}
</div>
@endsection

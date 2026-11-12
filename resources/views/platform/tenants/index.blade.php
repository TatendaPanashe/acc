@extends('layouts.platform')

@section('title', 'Tenants')
@use('App\Support\PlatformFormat', 'F')

@section('styles')
    .filters { display: grid; grid-template-columns: minmax(180px, 2fr) repeat(2, minmax(140px, 1fr)) auto; gap: 12px; align-items: end; margin-bottom: 20px; }
    .tenant-name { display: flex; align-items: center; gap: 12px; }
    .tenant-name strong { display: block; }
    .tenant-name span { color: var(--muted); font-size: 13px; }
    @media (max-width: 760px) { .filters { grid-template-columns: minmax(0, 1fr); } }
@endsection

@section('content')
<div class="page-head">
    <div><h1>Tenants & billing</h1><p>{{ number_format($tenants->total()) }} businesses</p></div>
</div>

<form method="GET" class="card filters" aria-label="Filter tenants">
    <div><label for="q">Search</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Business name or email"></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">Any status</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="plan">Plan</label><select id="plan" name="plan"><option value="">Any plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int) request('plan') === $plan->id)>{{ $plan->name }}</option>@endforeach</select></div>
    <div><button class="btn btn-primary"><x-platform.icon name="search" size="16"/> Filter</button></div>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Business</th><th>Country</th><th>Plan</th><th>Status</th><th class="num">MRR</th><th>Renews / trial ends</th><th>Registered</th></tr></thead>
            <tbody>
            @forelse($tenants as $company)
                @php $sub = $company->subscription; @endphp
                <tr>
                    <td><a class="tenant-name" href="{{ route('system.tenants.show', $company) }}" style="color:inherit; text-decoration:none">
                        <span class="avatar sm">{{ F::initials($company->name) }}</span>
                        <span><strong>{{ $company->name }}</strong><span>{{ $company->email }}</span></span>
                    </a></td>
                    <td>{{ config('platform.countries.'.$company->country, $company->country ?: '—') }}</td>
                    <td>{{ $sub?->plan?->name ?? '—' }} @if($sub?->billing_cycle === 'annual')<span class="muted">· annual</span>@endif</td>
                    <td>
                        @if($sub)
                            <span class="badge {{ $sub->trialExpired() ? 'expired' : $sub->status }}">{{ $sub->statusLabel() }}</span>
                            @if($sub->is_promotional && in_array($sub->status, ['active', 'past_due']))<span class="badge promo">Promo</span>@endif
                        @else
                            <span class="badge">No subscription</span>
                        @endif
                    </td>
                    <td class="num">{{ $sub ? F::money($sub->mrr()) : '—' }}</td>
                    <td>{{ $sub?->status === 'trialing' ? $sub->trial_ends_at?->format('d M Y') : ($sub?->promo_ends_at ?? $sub?->current_period_ends_at)?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $company->created_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No tenants match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $tenants->links() }}</div>
</div>
@endsection

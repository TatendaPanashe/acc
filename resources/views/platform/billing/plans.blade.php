@extends('layouts.platform')
@section('title', 'Plans')
@use('App\Support\PlatformFormat', 'F')
@section('styles')
    .plan-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .feature-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 16px; margin: 12px 0; }
    .feature-list label { display: flex; gap: 8px; align-items: center; font-weight: 400; color: var(--text); margin: 0; }
    .row-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 1100px) { .plan-grid { grid-template-columns: minmax(0, 1fr); } }
@endsection
@section('content')
<div class="page-head"><div><h1>Plans</h1><p>Prices, limits and the features each plan includes</p></div></div>
<div class="grid plan-grid">
    @foreach($plans as $plan)
        <form method="POST" action="{{ route('system.plans.update', $plan) }}" class="card">
            @csrf @method('PUT')
            <div class="card-head"><h2>{{ $plan->name }} @if($plan->is_featured)<span class="count-pill">Most popular</span>@endif @if($plan->is_custom)<span class="count-pill">Custom pricing</span>@endif</h2>
                <label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Available</label></div>
            <div class="row-3">
                <div><label>Monthly price ($)</label><input type="number" step="0.01" min="0" name="monthly_price" value="{{ $plan->monthly_price }}" required></div>
                <div><label>Annual price ($)</label><input type="number" step="0.01" min="0" name="annual_price" value="{{ $plan->annual_price }}" required></div>
                <div><label>Trial</label><input type="text" value="{{ $plan->trial_days }} days" disabled></div>
            </div>
            <div class="row-3" style="margin-top:10px">
                @foreach(['users', 'branches', 'companies'] as $metric)
                    <div><label>{{ $features[$metric][0] }}</label><input type="number" min="1" name="limits[{{ $metric }}]" value="{{ $plan->limit($metric) }}" placeholder="Unlimited"></div>
                @endforeach
            </div>
            <div class="feature-list">
                @foreach($features->filter(fn ($f) => $f[1] === 'feature') as $key => [$label])
                    <label><input type="checkbox" name="features[]" value="{{ $key }}" @checked($plan->hasFeature($key))> {{ $label }}</label>
                @endforeach
            </div>
            <button class="btn btn-primary">Save {{ $plan->name }}</button>
        </form>
    @endforeach
</div>
@endsection

@extends('layouts.platform')
@section('title', 'Coupons')
@section('styles')
    .coupon-form { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; align-items: end; }
    .coupon-form .wide { grid-column: span 2; }
    @media (max-width: 900px) { .coupon-form { grid-template-columns: minmax(0, 1fr); } .coupon-form .wide { grid-column: auto; } }
@endsection
@section('content')
<div class="page-head"><div><h1>Coupons</h1><p>Discount codes customers can enter when choosing a plan</p></div></div>
<form method="POST" action="{{ route('system.coupons.store') }}" class="card coupon-form" style="margin-bottom:20px">
    @csrf
    <div><label>Code</label><input type="text" name="code" required maxlength="40" placeholder="LAUNCH20" value="{{ old('code') }}"></div>
    <div><label>Type</label><select name="discount_type"><option value="percent">Percent off</option><option value="fixed">Fixed amount off ($)</option></select></div>
    <div><label>Value</label><input type="number" step="0.01" min="0.01" name="discount_value" required value="{{ old('discount_value') }}"></div>
    <div><label>Applies to</label><select name="duration"><option value="once">First invoice only</option><option value="forever">Every invoice</option></select></div>
    <div><label>Max redemptions</label><input type="number" min="1" name="max_redemptions" placeholder="Unlimited"></div>
    <div><label>Expires</label><input type="text" name="expires_at" placeholder="YYYY-MM-DD"></div>
    <div class="wide"><label>Plans (none = all)</label><select name="applies_to_plans[]" multiple size="2">@foreach($plans as $plan)<option value="{{ $plan->code }}">{{ $plan->name }}</option>@endforeach</select></div>
    <div class="wide"><label>Description</label><input type="text" name="description" maxlength="255"></div>
    <div><button class="btn btn-primary">Create coupon</button></div>
</form>
<div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Code</th><th>Discount</th><th>Plans</th><th class="num">Used</th><th>Expires</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($coupons as $coupon)
        <tr>
            <td><strong>{{ $coupon->code }}</strong><span class="muted" style="display:block;font-size:12px">{{ $coupon->description }}</span></td>
            <td>{{ $coupon->label() }}</td>
            <td>{{ $coupon->applies_to_plans ? collect($coupon->applies_to_plans)->map(fn ($c) => ucfirst($c))->join(', ') : 'All plans' }}</td>
            <td class="num">{{ $coupon->times_redeemed }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }}</td>
            <td>{{ $coupon->expires_at?->format('d M Y') ?? '—' }}</td>
            <td><span class="badge {{ $coupon->is_active ? 'active' : 'cancelled' }}">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td><form method="POST" action="{{ route('system.coupons.toggle', $coupon) }}">@csrf<button class="btn btn-ghost">{{ $coupon->is_active ? 'Deactivate' : 'Activate' }}</button></form></td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No coupons yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection

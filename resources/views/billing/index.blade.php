@extends('layouts.main')

@section('title', 'Billing & Plan')

@section('content')
@include('accounting.partials.page-header', ['title' => 'Billing & Plan', 'section' => 'Company'])

@php
    $featureLabels = collect(config('billing.features'));
    $limitText = fn ($value, $noun) => $value === null ? 'Custom '.$noun : $value.' '.str($noun)->singular()->plural($value);
    $statusTone = ['open' => 'success', 'locked' => 'danger'][$access['state']] ?? 'secondary';
@endphp

@if($access['state'] === 'locked')
    <div class="alert alert-danger">
        <strong>Workspace locked.</strong> {{ $access['message'] }}
        @if($openInvoice) <a href="{{ route('billing.invoices.show', $openInvoice) }}" class="alert-link">Pay invoice {{ $openInvoice->number }}</a>@endif
    </div>
@endif

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="header"><h2><strong>Current</strong> subscription</h2></div>
            <div class="body">
                @if($subscription)
                    <h4 class="mb-1">{{ $subscription->plan->name }} <span class="badge bg-{{ $statusTone }}">{{ $subscription->statusLabel() }}</span></h4>
                    <p class="text-muted mb-3">
                        @if($subscription->status === 'trialing')
                            Free trial · {{ $access['state'] === 'open' ? ($access['days_left'].' '.str('day')->plural($access['days_left']).' left, ends '.$subscription->trial_ends_at->format('d M Y')) : 'ended '.$subscription->trial_ends_at->format('d M Y') }}
                        @elseif($subscription->is_promotional)
                            Promotional until {{ $subscription->promo_ends_at?->format('d M Y') }}
                        @elseif(in_array($subscription->status, ['active', 'past_due']))
                            ${{ number_format((float) $subscription->price, 2) }} / {{ $subscription->billing_cycle === 'annual' ? 'year' : 'month' }} · renews {{ $subscription->current_period_ends_at?->format('d M Y') }}
                        @endif
                    </p>
                @endif
                <h6 class="text-muted text-uppercase small">Usage</h6>
                @foreach($usage as $metric => $row)
                    @php
                        $pct = $row['limit'] ? min(100, round($row['used'] / $row['limit'] * 100)) : 0;
                        $bar = $row['limit'] && $row['used'] > $row['limit'] ? 'bg-danger' : ($row['limit'] && $row['used'] == $row['limit'] ? 'bg-warning' : 'bg-success');
                    @endphp
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span>{{ $row['label'] }}</span><span>{{ $row['used'] }} of {{ $row['limit'] ?? 'unlimited' }}{{ $row['limit'] && $row['used'] > $row['limit'] ? ' · over plan limit' : '' }}</span></div>
                        @if($row['limit'])<div class="progress" style="height:6px"><div class="progress-bar {{ $bar }}" style="width: {{ $pct }}%"></div></div>@endif
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <div class="header"><h2><strong>Invoices</strong></h2></div>
            <div class="body table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Invoice</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('billing.invoices.show', $invoice) }}">{{ $invoice->number }}</a><small class="d-block text-muted">{{ $invoice->issued_at->format('d M Y') }}</small></td>
                            <td class="text-end">{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                            <td><span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : 'warning text-dark' }}">{{ $invoice->status === 'pending' ? 'Awaiting payment' : 'Paid' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">No invoices yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2><strong>Choose</strong> a plan</h2>
                <div class="btn-group" role="group" aria-label="Billing period">
                    <input type="radio" class="btn-check" name="cycle_toggle" id="cycle_monthly" value="monthly" checked>
                    <label class="btn btn-outline-primary btn-sm" for="cycle_monthly">Monthly</label>
                    <input type="radio" class="btn-check" name="cycle_toggle" id="cycle_annual" value="annual">
                    <label class="btn btn-outline-primary btn-sm" for="cycle_annual">Annual · 2 months free</label>
                </div>
            </div>
            <div class="body">
                <div class="row g-3">
                    @php $previous = null; @endphp
                    @foreach($plans as $plan)
                        @php
                            $order = array_flip(array_keys(config('billing.features')));
                            $own = $plan->features->whereNull('limit_value')->pluck('feature_key')->reject(fn ($k) => in_array($k, ['users', 'branches', 'companies']))->sortBy(fn ($k) => $order[$k] ?? 999)->values();
                            $new = $previous ? $own->diff($previous->features->pluck('feature_key')) : $own;
                            $isCurrent = $subscription && $subscription->platform_plan_id === $plan->id && in_array($subscription->status, ['active', 'past_due']);
                        @endphp
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 d-flex flex-column {{ $plan->is_featured ? 'border-primary border-2' : '' }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div><small class="text-muted">{{ $plan->is_featured ? '' : $plan->tagline }}</small><h5 class="mb-0">{{ $plan->name }}</h5></div>
                                    @if($plan->is_featured)<span class="badge bg-primary">Most popular</span>@endif
                                </div>
                                <div class="my-2">
                                    <span class="h3 mb-0 price" data-monthly="{{ number_format((float) $plan->monthly_price, 0) }}" data-annual="{{ number_format((float) $plan->annual_price, 0) }}">{{ $plan->is_custom ? 'From ' : '' }}${{ number_format((float) $plan->monthly_price, 0) }}</span>
                                    <span class="text-muted per" data-monthly="per month" data-annual="per year">per month</span>
                                </div>
                                <p class="text-muted small mb-2">{{ $plan->description }}</p>
                                <ul class="small ps-3 mb-3">
                                    @if($previous)<li>Everything in {{ $previous->name }}</li>@endif
                                    @foreach($new as $key)<li>{{ $featureLabels[$key][0] ?? $key }}</li>@endforeach
                                    <li>{{ $limitText($plan->limit('users'), 'users') }} · {{ $limitText($plan->limit('branches'), 'branches') }} · {{ $limitText($plan->limit('companies'), 'companies') }}</li>
                                </ul>
                                <div class="mt-auto">
                                    @if($plan->is_custom)
                                        <a href="mailto:{{ config('billing.sales_email') }}?subject={{ rawurlencode($plan->name.' plan for '.$company->name) }}" class="btn btn-outline-primary w-100">Contact us</a>
                                    @elseif($isCurrent)
                                        <button class="btn btn-light w-100" disabled>Current plan</button>
                                    @else
                                        <form method="POST" action="{{ route('billing.checkout') }}" class="checkout-form">
                                            @csrf
                                            <input type="hidden" name="plan" value="{{ $plan->code }}">
                                            <input type="hidden" name="billing_cycle" value="monthly" class="cycle-input">
                                            <input type="hidden" name="coupon" class="coupon-input">
                                            <button class="btn {{ $plan->is_featured ? 'btn-primary' : 'btn-outline-primary' }} w-100">Choose {{ $plan->name }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @php $previous = $plan; @endphp
                    @endforeach
                </div>
                <div class="row mt-3">
                    <div class="col-md-6"><label class="form-label" for="coupon">Coupon code</label><input id="coupon" class="form-control" placeholder="Optional" maxlength="40" value="{{ old('coupon') }}"></div>
                </div>
                <small class="text-muted d-block mt-2">Prices in USD. Changing plan applies from the next invoice once paid. You will be taken to a secure payment page.</small>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    function cycle() { return document.querySelector('input[name=cycle_toggle]:checked').value; }
    function sync() {
        var c = cycle();
        document.querySelectorAll('.price').forEach(function (el) { el.textContent = el.textContent.replace(/\$[\d,]+/, '$' + el.dataset[c]); });
        document.querySelectorAll('.per').forEach(function (el) { el.textContent = el.dataset[c]; });
        document.querySelectorAll('.cycle-input').forEach(function (el) { el.value = c; });
    }
    document.querySelectorAll('input[name=cycle_toggle]').forEach(function (el) { el.addEventListener('change', sync); });
    document.querySelectorAll('.checkout-form').forEach(function (form) {
        form.addEventListener('submit', function () { form.querySelector('.coupon-input').value = document.getElementById('coupon').value; });
    });
})();
</script>
@endpush
@endsection

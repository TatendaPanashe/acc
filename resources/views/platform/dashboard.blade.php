@extends('layouts.platform')

@section('title', 'Overview')

@use('App\Support\PlatformFormat', 'F')
@php
    $vs = $previous->start->format('M j').' – '.$previous->end->format('M j');
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
@endphp

@section('styles')
    .overview { grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr); }
    .overview .side { grid-row: span 2; display: flex; flex-direction: column; }
    .hero-row { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
    .hero-label { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0 0 6px; }
    .hero-value { font-size: clamp(48px, 7vw, 76px); font-weight: 800; line-height: 1.05; letter-spacing: -0.03em; color: var(--accent); margin: 6px 0 14px; }
    .hero-value small { font-size: 0.4em; font-weight: 700; letter-spacing: 0; margin-left: 8px; }
    .hero-side { text-align: right; }
    .hero-side .arr { font-size: 26px; font-weight: 700; display: block; }
    .hero-side .muted { font-size: 13px; }
    .tiles-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .stat-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .stat-label { font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 6px; }
    .stat-value { font-size: 32px; font-weight: 700; color: var(--accent); margin: 8px 0 10px; letter-spacing: -0.01em; }
    .stat-value a { color: inherit; }
    .stat-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; font-size: 13px; color: var(--muted); }
    .column-chart { width: 100%; height: auto; display: block; overflow: visible; }
    .column-chart .axis-label { font-size: 12px; fill: var(--muted); font-variant-numeric: tabular-nums; }
    .column-chart .axis-label.current { fill: var(--text); font-weight: 600; }
    .column-chart .value-label { font-size: 13px; font-weight: 600; fill: var(--text); }
    .column-chart .hit { cursor: default; outline: none; }
    .column-chart .hit:hover, .column-chart .hit:focus { fill: var(--accent); fill-opacity: 0.06; }
    .sub-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; flex: 1; }
    .sub-list li a { display: flex; align-items: center; gap: 14px; padding: 12px 6px; border-radius: 10px; color: var(--text); text-decoration: none; }
    .sub-list li a:hover { background: var(--surface-2); }
    .sub-list .name { flex: 1; min-width: 0; }
    .sub-list .name strong { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sub-list .name span { color: var(--muted); font-size: 13px; }
    .sub-list .amount { font-weight: 700; color: var(--accent); white-space: nowrap; }
    .sub-list .amount span { color: var(--muted); font-weight: 400; font-size: 13px; }
    .halves { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ranked { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
    .ranked li { display: grid; grid-template-columns: minmax(90px, 150px) minmax(0, 1fr); gap: 12px; align-items: center; outline: none; border-radius: 8px; }
    .ranked li:hover, .ranked li:focus { background: var(--surface-2); }
    .ranked-label { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ranked-track { display: flex; align-items: center; gap: 10px; }
    .ranked-bar { height: 12px; background: var(--accent); border-radius: 0 4px 4px 0; flex: none; }
    .ranked-value { font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .event-list { list-style: none; margin: 0; padding: 0; }
    .event-list li { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
    .event-list li:last-child { border-bottom: 0; }
    .event-list .why { color: var(--muted); font-size: 13px; display: block; }
    .period-form { display: flex; align-items: center; gap: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 4px 6px 4px 14px; color: var(--muted); }
    .period-form select { border: 0; padding: 8px 6px; font-weight: 600; width: auto; background: transparent; }
    @media (max-width: 1200px) { .tiles-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 960px) { .overview, .halves { grid-template-columns: minmax(0, 1fr); } .overview .side { grid-row: auto; } }
    @media (max-width: 560px) {
        .tiles-4 { grid-template-columns: minmax(0, 1fr); }
        .hero-row { flex-direction: column; }
        .hero-side { text-align: left; }
        .hero-side .tile-icon { display: none; }
        .card { padding: 18px; }
        .ranked li { grid-template-columns: minmax(80px, 110px) minmax(0, 1fr); }
    }
@endsection

@section('content')
<div class="page-head">
    <div>
        <h1>Overview</h1>
        <p>{{ config('platform.name') }} platform · all tenant businesses</p>
    </div>
    <form method="GET" class="period-form" aria-label="Reporting period">
        <x-platform.icon name="calendar" size="18"/>
        <select name="month" onchange="this.form.submit()" aria-label="Month">
            @foreach($months as $value => $label)
                <option value="{{ $value }}" @selected($metrics->start->format('Y-m') === $value)>{{ $value === now()->format('Y-m') ? $metrics->label() : $label }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-ghost">Go</button></noscript>
    </form>
</div>

<div class="grid overview">
    {{-- Hero: MRR, with ARR beside it --}}
    <section class="card">
        <div class="hero-row">
            <div>
                <h2 class="hero-label">Monthly recurring revenue <span class="info-dot" tabindex="0" data-tip="Sum of paying subscriptions normalised to one month (annual plans ÷ 12). Trials and promotional subscriptions add nothing." aria-label="How MRR is calculated"><x-platform.icon name="info" size="17"/></span></h2>
                <div class="hero-value">{{ F::money($mrr) }}<small>MRR</small></div>
                <x-platform.delta :change="$mrrChange" :vs="$vs.' ('.F::money($previousMrr).' MRR)'"/>
            </div>
            <div class="hero-side">
                <span class="tile-icon" style="margin-left:auto; margin-bottom:14px"><x-platform.icon name="trending-up" size="24"/></span>
                <span class="muted">Annual recurring revenue</span>
                <span class="arr">{{ F::money($arr) }}</span>
                <span class="muted">ARR = MRR × 12</span>
            </div>
        </div>
    </section>

    {{-- Highest-value subscriptions --}}
    <section class="card side">
        <div class="card-head">
            <h2>Active subscriptions <span class="count-pill">{{ number_format($accounts['active']) }}</span></h2>
            <a href="{{ route('system.tenants.index', ['status' => 'active']) }}">View all</a>
        </div>
        @if($topSubscriptions->isEmpty())
            <p class="muted" style="flex:1">No paying subscriptions yet.</p>
        @else
            <ul class="sub-list">
                @foreach($topSubscriptions as $sub)
                    <li><a href="{{ route('system.tenants.show', $sub->company_id) }}">
                        <span class="avatar">{{ F::initials($sub->company?->name) }}</span>
                        <span class="name"><strong>{{ $sub->company?->name }}</strong><span>{{ $sub->plan?->name }} plan · {{ $sub->billing_cycle === 'annual' ? 'annual' : 'monthly' }}</span></span>
                        <span class="amount">{{ F::money($sub->mrr()) }} <span>/mo</span></span>
                    </a></li>
                @endforeach
            </ul>
        @endif
        <a href="{{ route('system.tenants.index') }}" class="btn btn-outline" style="margin-top:16px"><x-platform.icon name="layers" size="18"/> Manage tenants & subscriptions</a>
    </section>

    {{-- MRR growth --}}
    <section class="card">
        <div class="card-head"><h2>MRR growth <span class="sub">last 12 months · month-end MRR</span></h2></div>
        <x-platform.column-chart :points="$mrrHistory->map(fn ($p) => ['label' => $p['label'], 'value' => $p['mrr'], 'current' => $p['current']])->all()" name="MRR"/>
    </section>
</div>

{{-- MRR movements --}}
<div class="grid tiles-4" style="margin-top:20px">
    <x-platform.stat-tile label="New MRR" icon="user-plus" :value="F::money($movements['new'])" :change="$movementChanges['new']" :vs="$vs" info="MRR from businesses that became paying customers this period."/>
    <x-platform.stat-tile label="Expansion MRR" icon="trending-up" :value="F::money($movements['expansion'])" :change="$movementChanges['expansion']" :vs="$vs" info="Extra MRR from upgrades and plan changes."/>
    <x-platform.stat-tile label="Churned MRR" icon="trending-down" :value="F::money($movements['churn'] + $movements['contraction'])" :change="$movementChanges['lost']" :up-is-good="false" :vs="$vs" info="MRR lost to cancellations, suspensions and downgrades."/>
    <x-platform.stat-tile label="Net new MRR" icon="dollar" :value="F::money($movements['net'])" :change="$movementChanges['net']" :vs="$vs" info="New + expansion + reactivation − churn − downgrades."/>
</div>

{{-- Accounts --}}
<div class="grid tiles-4" style="margin-top:20px">
    <x-platform.stat-tile label="Registered businesses" icon="briefcase" :value="number_format($totalBusinesses)" :href="route('system.tenants.index')" info="All tenant companies registered up to the end of the period.">
        <span>+{{ number_format($registrations) }} this period</span>
    </x-platform.stat-tile>
    <x-platform.stat-tile label="Active subscriptions" icon="check" :value="number_format($accounts['active'])" :href="route('system.tenants.index', ['status' => 'active'])" info="Right now. Includes past-due and promotional subscriptions.">
        <span>{{ number_format($accounts['paying']) }} paying · {{ number_format($accounts['promotional']) }} promotional @if($accounts['past_due']) · {{ $accounts['past_due'] }} past due @endif</span>
    </x-platform.stat-tile>
    <x-platform.stat-tile label="Trial accounts" icon="clock" :value="number_format($accounts['trial'])" :href="route('system.tenants.index', ['status' => 'trialing'])" info="Right now, trials that have not yet ended.">
        <span>{{ number_format($accounts['trial_expired']) }} expired, not converted</span>
    </x-platform.stat-tile>
    <x-platform.stat-tile label="Suspended tenants" icon="pause" :value="number_format($accounts['suspended'])" :href="route('system.tenants.index', ['status' => 'suspended'])" info="Right now. Users of suspended tenants cannot sign in.">
        <span>{{ number_format($accounts['cancelled']) }} cancelled in total</span>
    </x-platform.stat-tile>
</div>

{{-- Billing health & growth --}}
<div class="grid tiles-4" style="margin-top:20px">
    <x-platform.stat-tile label="Failed payments" icon="alert" :value="number_format($failed['count'])" :change="$failedChange" :up-is-good="false" :vs="$vs" info="Payment attempts that failed this period.">
        <span>{{ F::money($failed['amount']) }} not collected</span>
    </x-platform.stat-tile>
    <x-platform.stat-tile label="Subscription cancellations" icon="x-circle" :value="number_format($cancellations['count'])" :change="$cancellationsChange" :up-is-good="false" :vs="$vs" info="Subscriptions cancelled this period.">
        <span>{{ F::money($cancellations['lost_mrr']) }} MRR lost</span>
    </x-platform.stat-tile>
    <x-platform.stat-tile label="New registrations" icon="user-plus" :value="number_format($registrations)" :change="$registrationsChange" :vs="$vs" info="Businesses that signed up this period."/>
    @php $cohort = $registrationHistory->last(); @endphp
    <x-platform.stat-tile label="Trial → paid conversion" icon="repeat" :value="$pct($conversionRate)" info="Of the businesses registered this period whose trial has finished, the share that became paying customers. Businesses still on trial are not counted yet.">
        <span>{{ number_format($cohort['converted']) }} of {{ number_format($cohort['converted'] + $cohort['lapsed']) }} finished trials @if($cohort['pending']) · {{ number_format($cohort['pending']) }} still on trial @endif</span>
    </x-platform.stat-tile>
</div>

{{-- Revenue mix --}}
<div class="grid halves" style="margin-top:20px">
    <section class="card">
        <div class="card-head"><h2><x-platform.icon name="globe" size="18" class="muted"/> Revenue by country</h2><span class="sub">{{ F::money($revenue) }} collected</span></div>
        <x-platform.ranked-bars :rows="$revenueByCountry->take(8)" empty="No payments collected this period."/>
    </section>
    <section class="card">
        <div class="card-head"><h2><x-platform.icon name="card" size="18" class="muted"/> Revenue by payment provider</h2><span class="sub">{{ F::money($revenue) }} collected</span></div>
        <x-platform.ranked-bars :rows="$revenueByProvider" empty="No payments collected this period."/>
    </section>
</div>

{{-- Registrations & conversion, and what needs attention --}}
<div class="grid halves" style="margin-top:20px">
    <section class="card">
        <div class="card-head"><h2>New registrations <span class="sub">last 12 months</span></h2></div>
        @php
            $registrationPoints = $registrationHistory->map(fn ($r) => [
                'label' => $r['label'], 'value' => $r['registered'], 'current' => $r['current'],
                'tip' => PHP_EOL.$r['converted'].' converted · '.$r['lapsed'].' did not convert · '.$r['pending'].' still on trial',
                'extra' => [$r['converted'], $r['lapsed'], $r['pending'], $pct($r['rate'])],
            ]);
            $mrrPoints = $mrrHistory->map(fn ($p) => ['label' => $p['label'], 'value' => $p['mrr'], 'current' => $p['current']]);
        @endphp
        <x-platform.column-chart :money="false" name="Registrations" :width="520" :height="250" :points="$registrationPoints">
            <x-slot:extraHead><th class="num">Converted</th><th class="num">Did not convert</th><th class="num">On trial</th><th class="num">Conversion</th></x-slot:extraHead>
        </x-platform.column-chart>
    </section>
    <section class="card">
        <div class="card-head"><h2>Needs attention</h2><a href="{{ route('system.tenants.index', ['status' => 'past_due']) }}">Past due accounts</a></div>
        <h3 class="muted" style="font-size:13px; text-transform:uppercase; letter-spacing:.04em; margin:0 0 4px">Failed payments</h3>
        <ul class="event-list">
            @forelse($failed['recent'] as $payment)
                <li><span><a href="{{ route('system.tenants.show', $payment->company_id) }}">{{ $payment->company?->name }}</a><span class="why">{{ $payment->failure_reason ?? 'Payment failed' }} · {{ config('platform.payment_providers.'.$payment->provider, $payment->provider) }}</span></span><span class="num">{{ F::money((float) $payment->amount, true) }}<span class="why">{{ $payment->created_at->format('M j') }}</span></span></li>
            @empty
                <li class="muted">No failed payments this period.</li>
            @endforelse
        </ul>
        <h3 class="muted" style="font-size:13px; text-transform:uppercase; letter-spacing:.04em; margin:18px 0 4px">Cancellations</h3>
        <ul class="event-list">
            @forelse($cancellations['recent'] as $event)
                <li><span><a href="{{ route('system.tenants.show', $event->company_id) }}">{{ $event->company?->name }}</a><span class="why">{{ $event->details['reason'] ?? 'No reason given' }}</span></span><span class="num">−{{ F::money(abs((float) $event->mrr_delta)) }}/mo<span class="why">{{ $event->occurred_at->format('M j') }}</span></span></li>
            @empty
                <li class="muted">No cancellations this period.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection

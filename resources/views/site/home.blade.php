@extends('layouts.site')

@section('title', 'Cloud accounting for growing businesses')

@section('styles')
    .hero { padding: 72px 0 64px; background: radial-gradient(1200px 500px at 85% -10%, var(--blue-soft), transparent 60%), radial-gradient(900px 400px at -10% 110%, var(--teal-soft), transparent 60%); }
    .hero-grid { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); gap: 48px; align-items: center; }
    .hero h1 span { color: var(--blue); }
    .hero .cta { display: flex; gap: 12px; flex-wrap: wrap; margin: 28px 0 18px; }
    .hero-notes { display: flex; gap: 18px; flex-wrap: wrap; color: var(--muted); font-size: 14px; }
    .hero-notes span { display: inline-flex; align-items: center; gap: 6px; }
    .mock { background: #fff; border: 1px solid var(--line); border-radius: 18px; box-shadow: 0 30px 60px -20px rgba(22,32,51,.25); overflow: hidden; }
    .mock-bar { display: flex; gap: 6px; padding: 12px 14px; border-bottom: 1px solid var(--line); background: var(--bg); }
    .mock-bar i { width: 10px; height: 10px; border-radius: 50%; background: var(--line); }
    .mock-body { padding: 20px; display: grid; gap: 14px; }
    .mock-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .mock-tile { border: 1px solid var(--line); border-radius: 12px; padding: 12px; }
    .mock-tile small { color: var(--muted); font-size: 11px; display: block; }
    .mock-tile b { font-size: 18px; color: var(--blue); }
    .mock-chart { border: 1px solid var(--line); border-radius: 12px; padding: 14px; display: flex; align-items: flex-end; gap: 8px; height: 150px; }
    .mock-chart span { flex: 1; background: #a9c5f1; border-radius: 4px 4px 0 0; }
    .mock-chart span:last-child { background: var(--blue); }
    .mock-rows { border: 1px solid var(--line); border-radius: 12px; }
    .mock-rows div { display: flex; justify-content: space-between; padding: 9px 12px; font-size: 13px; border-bottom: 1px solid var(--line); }
    .mock-rows div:last-child { border-bottom: 0; }
    .pill { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--teal-soft); color: var(--teal); }
    .facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: -28px; position: relative; }
    .fact { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px 20px; display: flex; gap: 12px; align-items: center; box-shadow: 0 8px 20px -12px rgba(22,32,51,.18); }
    .fact strong { display: block; }
    .fact span { color: var(--muted); font-size: 14px; }
    .module-card { text-decoration: none; color: inherit; transition: border-color .15s, transform .15s; display: block; }
    .module-card:hover { border-color: var(--blue); transform: translateY(-2px); }
    .module-card h3 { margin-bottom: 6px; }
    .split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 48px; align-items: center; }
    .steps { counter-reset: step; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 22px; }
    .step { position: relative; }
    .step::before { counter-increment: step; content: counter(step); width: 38px; height: 38px; border-radius: 50%; background: var(--blue); color: #fff; font-weight: 700; display: grid; place-items: center; margin-bottom: 14px; }
    .price-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 32px; }
    .price-card { text-align: left; }
    .price-card .amount { font-size: 32px; font-weight: 800; color: var(--ink); }
    .price-card.featured { border-color: var(--blue); box-shadow: 0 0 0 2px var(--blue) inset; }
    @media (max-width: 980px) { .hero-grid, .split { grid-template-columns: minmax(0, 1fr); } .facts, .steps, .price-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 600px) { .facts, .steps, .price-row { grid-template-columns: minmax(0, 1fr); } .facts { margin-top: 24px; } .mock-tiles { gap: 6px; } .mock-tile { padding: 8px; } .mock-tile b { font-size: 14px; } .mock-tile small { font-size: 10px; } }
@endsection

@section('content')
<div class="hero">
    <div class="wrap hero-grid">
        <div>
            <span class="eyebrow">Accounting · Inventory · Point of sale</span>
            <h1>Run your books, stock and sales <span>in one place</span>.</h1>
            <p class="lead">acc263 is online accounting built for businesses in Zimbabwe and the region: invoicing, payables, inventory, POS, bank reconciliation, multi-currency and ZIMRA-ready tax, with reports that are always up to date.</p>
            <div class="cta">
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Start your 14-day free trial <x-platform.icon name="arrow-right" size="18"/></a>
                <a href="{{ route('site.services') }}" class="btn btn-ghost btn-lg">See everything it does</a>
            </div>
            <div class="hero-notes">
                <span><x-platform.icon name="check" size="16"/> No card needed for the trial</span>
                <span><x-platform.icon name="check" size="16"/> Set up in minutes</span>
                <span><x-platform.icon name="check" size="16"/> Plans from ${{ number_format((float) ($plans->first()->monthly_price ?? 19), 0) }}/month</span>
            </div>
        </div>
        <div class="mock" aria-hidden="true">
            <div class="mock-bar"><i></i><i></i><i></i></div>
            <div class="mock-body">
                <div class="mock-tiles">
                    <div class="mock-tile"><small>Sales this month</small><b>$48,250</b></div>
                    <div class="mock-tile"><small>Owed to you</small><b>$12,900</b></div>
                    <div class="mock-tile"><small>Bank (reconciled)</small><b>$31,480</b></div>
                </div>
                <div class="mock-chart">@foreach([38, 46, 41, 55, 60, 52, 68, 74, 70, 82, 88, 96] as $h)<span style="height: {{ $h }}%"></span>@endforeach</div>
                <div class="mock-rows">
                    <div><span>INV-1042 · Sunrise Foods</span><span class="pill">Paid</span></div>
                    <div><span>POS sale · Main branch</span><span>$86.40</span></div>
                    <div><span>Supplier invoice · awaiting approval</span><span class="pill" style="background:#fff3db;color:#8a5a00">Review</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="wrap">
    <div class="facts">
        <div class="fact"><span class="icon-badge" style="margin:0"><x-platform.icon name="clock"/></span><div><strong>14-day free trial</strong><span>Full features of your plan</span></div></div>
        <div class="fact"><span class="icon-badge teal" style="margin:0"><x-platform.icon name="globe"/></span><div><strong>Multi-currency</strong><span>USD, ZiG, Rand and more</span></div></div>
        <div class="fact"><span class="icon-badge" style="margin:0"><x-platform.icon name="check"/></span><div><strong>Tax-ready</strong><span>VAT codes and ZIMRA FDMS</span></div></div>
        <div class="fact"><span class="icon-badge teal" style="margin:0"><x-platform.icon name="shield"/></span><div><strong>Controls built in</strong><span>Roles, approvals, period locks</span></div></div>
    </div>
</div>

<section>
    <div class="wrap">
        <div class="center" style="margin-bottom:40px">
            <span class="eyebrow">Everything in one system</span>
            <h2>One login for the whole back office</h2>
            <p class="lead">Every invoice, receipt, stock movement and payment posts to the ledger automatically, so your reports are right without re-typing anything.</p>
        </div>
        <div class="grid g3">
            @foreach(config('site.modules') as $key => [$title, $icon, $summary])
                <a href="{{ route('site.services') }}#{{ $key }}" class="card module-card">
                    <span class="icon-badge {{ $loop->odd ? '' : 'teal' }}"><x-platform.icon :name="$icon"/></span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $summary }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="section-alt">
    <div class="wrap split">
        <div>
            <span class="eyebrow">Accurate by design</span>
            <h2>Books you can trust, every day</h2>
            <p class="lead">acc263 follows proper accounting rules so mistakes are caught before they reach your reports.</p>
            <ul class="ticks">
                <li>Double-entry: every posting balances, or it is not saved</li>
                <li>Closed months stay closed; posted entries cannot be edited or deleted</li>
                <li>Stock value and the ledger are checked against each other</li>
                <li>Bank statements are reconciled against your books</li>
                <li>Every change is recorded with who made it</li>
            </ul>
        </div>
        <div class="grid g2">
            <div class="card"><span class="icon-badge"><x-platform.icon name="users"/></span><h3>Clear roles</h3><p>Company Admin, Finance Manager, Accountant and Auditor, each seeing only what they need.</p></div>
            <div class="card"><span class="icon-badge teal"><x-platform.icon name="check"/></span><h3>Approvals</h3><p>Accountants capture the work; managers approve journals, supplier invoices and payments.</p></div>
            <div class="card"><span class="icon-badge teal"><x-platform.icon name="lock"/></span><h3>Your data, separate</h3><p>Each business's records are kept apart from every other business.</p></div>
            <div class="card"><span class="icon-badge"><x-platform.icon name="monitor"/></span><h3>Web and desktop</h3><p>Use it in the browser, or install the Windows desktop app.</p></div>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="center" style="margin-bottom:40px">
            <span class="eyebrow">How it works</span>
            <h2>From sign-up to first invoice in an afternoon</h2>
        </div>
        <div class="steps">
            <div class="step"><h3>Sign up</h3><p class="muted">Create your workspace and pick a plan for your free 14-day trial.</p></div>
            <div class="step"><h3>Set up</h3><p class="muted">A guided setup adds your company, currencies, VAT and a ready-made chart of accounts.</p></div>
            <div class="step"><h3>Invite your team</h3><p class="muted">Add colleagues with the right role for each person.</p></div>
            <div class="step"><h3>Work and grow</h3><p class="muted">Invoice, sell, buy and report. Choose a plan when your trial ends.</p></div>
        </div>
        <p class="center" style="margin-top:28px"><a href="{{ route('site.how') }}" class="btn btn-ghost">How we work in detail <x-platform.icon name="arrow-right" size="16"/></a></p>
    </div>
</section>

<section class="section-alt">
    <div class="wrap center">
        <span class="eyebrow">Simple pricing</span>
        <h2>Pick the plan that fits today</h2>
        <p class="lead">Every plan starts with a 14-day free trial. Pay monthly, or yearly and get two months free.</p>
        <div class="price-row">
            @foreach($plans as $plan)
                <div class="card price-card {{ $plan->is_featured ? 'featured' : '' }}">
                    <span class="eyebrow" style="margin-bottom:4px; min-height:1.2em">{{ $plan->is_featured ? 'Most popular' : $plan->tagline }}</span>
                    <h3>{{ $plan->name }}</h3>
                    <div class="amount">{{ $plan->is_custom ? 'From ' : '' }}${{ number_format((float) $plan->monthly_price, 0) }}<small class="muted" style="font-size:15px;font-weight:500"> /month</small></div>
                    <p>{{ $plan->description }}</p>
                </div>
            @endforeach
        </div>
        <p style="margin-top:28px"><a href="{{ route('site.services') }}#pricing" class="btn btn-primary">Compare plans</a></p>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="cta-band">
            <div><h2>Ready to see it with your own numbers?</h2><p>Start free for 14 days. No card needed.</p></div>
            <a href="{{ route('register') }}" class="btn btn-light btn-lg">Start free trial</a>
        </div>
    </div>
</section>
@endsection

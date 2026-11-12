<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Cloud accounting for growing businesses') · acc263</title>
    <meta name="description" content="@yield('description', 'acc263 is online accounting for businesses in Zimbabwe and the region: ledger, invoicing, inventory, POS, multi-currency, ZIMRA-ready tax and reporting. Start a 14-day free trial.')">
    <link rel="icon" href="{{ asset('assets/images/favicon.ico') }}" type="image/x-icon">
    <style>
        :root {
            --ink: #162033; --muted: #5b6677; --line: #dfe5ee; --bg: #f5f7fb; --surface: #ffffff;
            --blue: #1957c2; --blue-dark: #12408f; --blue-soft: #e8f0fc; --teal: #0f766e; --teal-soft: #e3f4f1;
            --good: #067a3a; --radius: 16px; --font: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: var(--surface); color: var(--ink); font: 16px/1.6 var(--font); -webkit-font-smoothing: antialiased; }
        a { color: var(--blue); }
        img, svg { max-width: 100%; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
        .icon { flex: none; }

        /* header */
        .site-header { position: sticky; top: 0; z-index: 20; background: rgba(255,255,255,.92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
        .site-header .wrap { display: flex; align-items: center; justify-content: space-between; height: 68px; gap: 16px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 22px; color: var(--blue); text-decoration: none; letter-spacing: -0.01em; }
        .nav { display: flex; align-items: center; gap: 6px; }
        .nav a { color: var(--ink); text-decoration: none; font-weight: 500; padding: 8px 14px; border-radius: 10px; }
        .nav a:hover { background: var(--bg); }
        .nav a.active { color: var(--blue); background: var(--blue-soft); }
        .actions { display: flex; align-items: center; gap: 10px; }
        .menu { display: none; }
        .menu summary { list-style: none; cursor: pointer; padding: 8px; border-radius: 10px; display: flex; }
        .menu summary::-webkit-details-marker { display: none; }
        .menu[open] .menu-panel { display: flex; }
        .menu-panel { display: none; position: absolute; left: 0; right: 0; top: 68px; background: var(--surface); border-bottom: 1px solid var(--line); flex-direction: column; padding: 12px 24px 20px; gap: 4px; box-shadow: 0 12px 24px rgba(22,32,51,.08); }
        .menu-panel a { padding: 12px 4px; color: var(--ink); text-decoration: none; font-weight: 500; border-bottom: 1px solid var(--line); }

        /* buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; padding: 12px 20px; font: 600 15px var(--font); text-decoration: none; border: 1px solid transparent; cursor: pointer; white-space: nowrap; }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-primary:hover { background: var(--blue-dark); }
        .btn-ghost { background: var(--surface); color: var(--ink); border-color: var(--line); }
        .btn-ghost:hover { border-color: var(--blue); color: var(--blue); }
        .btn-light { background: #fff; color: var(--blue); }
        .btn-lg { padding: 15px 26px; font-size: 16px; }
        .btn:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 3px solid rgba(25,87,194,.35); outline-offset: 2px; }

        /* sections */
        section { padding: 80px 0; }
        .section-alt { background: var(--bg); }
        .eyebrow { display: inline-block; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--teal); margin-bottom: 10px; }
        h1, h2, h3 { line-height: 1.15; letter-spacing: -0.02em; margin: 0 0 14px; }
        h1 { font-size: clamp(36px, 5.2vw, 58px); font-weight: 800; }
        h2 { font-size: clamp(28px, 3.4vw, 40px); font-weight: 800; }
        h3 { font-size: 19px; font-weight: 700; letter-spacing: -0.01em; }
        .lead { font-size: clamp(17px, 1.6vw, 20px); color: var(--muted); max-width: 680px; }
        .center { text-align: center; } .center .lead { margin-left: auto; margin-right: auto; }
        .muted { color: var(--muted); }
        .grid { display: grid; gap: 22px; }
        .g2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .g3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .g4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); padding: 26px; }
        .card p { margin: 0; color: var(--muted); }
        .icon-badge { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: var(--blue-soft); color: var(--blue); margin-bottom: 16px; }
        .icon-badge.teal { background: var(--teal-soft); color: var(--teal); }
        .ticks { list-style: none; padding: 0; margin: 14px 0 0; display: grid; gap: 8px; }
        .ticks li { display: flex; gap: 10px; align-items: flex-start; color: var(--ink); font-size: 15px; }
        .ticks li::before { content: ""; flex: none; width: 18px; height: 18px; margin-top: 3px; border-radius: 50%; background: var(--teal-soft) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230f766e' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E") center/11px no-repeat; }

        /* CTA band */
        .cta-band { background: linear-gradient(135deg, var(--blue), var(--teal)); color: #fff; border-radius: 24px; padding: 56px; display: flex; justify-content: space-between; align-items: center; gap: 28px; flex-wrap: wrap; }
        .cta-band h2 { margin: 0 0 8px; }
        .cta-band p { margin: 0; opacity: .9; font-size: 18px; }

        /* footer */
        .site-footer { background: #0f1726; color: #c6cfdc; padding: 56px 0 28px; }
        .site-footer a { color: #e7ecf3; text-decoration: none; }
        .site-footer a:hover { text-decoration: underline; }
        .foot-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1.3fr; gap: 32px; }
        .foot-grid h4 { color: #fff; font-size: 14px; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 14px; }
        .foot-grid ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .copyright { border-top: 1px solid rgba(255,255,255,.1); margin-top: 40px; padding-top: 20px; font-size: 14px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

        @media (max-width: 980px) {
            .g3, .g4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .foot-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 820px) {
            .nav, .actions .hide-sm { display: none; }
            .menu { display: block; }
            section { padding: 56px 0; }
            .cta-band { padding: 36px 28px; }
        }
        @media (max-width: 600px) {
            .wrap { padding: 0 16px; }
            .g2, .g3, .g4, .foot-grid { grid-template-columns: minmax(0, 1fr); }
            .actions .btn { padding: 10px 14px; }
        }
        @yield('styles')
    </style>
</head>
<body>
@php
    $links = ['site.home' => 'Home', 'site.services' => 'Services', 'site.how' => 'How we work', 'site.contact' => 'Contact'];
@endphp
<header class="site-header">
    <div class="wrap">
        <a href="{{ route('site.home') }}" class="brand" aria-label="acc263 home">
            <svg width="32" height="32" viewBox="0 0 34 34" aria-hidden="true"><path d="M17 2l13 7.5v15L17 32 4 24.5v-15z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M11.5 17.5l4 4 7-8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            acc263
        </a>
        <nav class="nav" aria-label="Main">
            @foreach($links as $route => $label)
                <a href="{{ route($route) }}" class="{{ request()->routeIs($route.'*') ? 'active' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="actions">
            @auth
                <a href="{{ auth()->user()->is_superuser ? route('system.platform.dashboard') : route('dashboard') }}" class="btn btn-primary">Open acc263</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost hide-sm">Sign in</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Start free trial</a>
            @endauth
            <details class="menu">
                <summary aria-label="Menu"><x-platform.icon name="menu" size="24"/></summary>
                <div class="menu-panel">
                    @foreach($links as $route => $label)<a href="{{ route($route) }}">{{ $label }}</a>@endforeach
                    @guest<a href="{{ route('login') }}">Sign in</a>@endguest
                </div>
            </details>
        </div>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <a href="{{ route('site.home') }}" class="brand" style="color:#fff">acc263</a>
                <p style="max-width:340px">Online accounting, inventory and point of sale for businesses in Zimbabwe and the region, with multi-currency and ZIMRA-ready tax built in.</p>
            </div>
            <div>
                <h4>Explore</h4>
                <ul>@foreach($links as $route => $label)<li><a href="{{ route($route) }}">{{ $label }}</a></li>@endforeach</ul>
            </div>
            <div>
                <h4>Get started</h4>
                <ul>
                    <li><a href="{{ route('register') }}">Start free trial</a></li>
                    <li><a href="{{ route('site.services') }}#pricing">Pricing</a></li>
                    <li><a href="{{ route('login') }}">Sign in</a></li>
                    <li><a href="{{ route('portal.login') }}">Client portal</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <ul>
                    <li><a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a></li>
                    @if(config('site.phone'))<li><a href="tel:{{ preg_replace('/[^\d+]/', '', config('site.phone')) }}">{{ config('site.phone') }}</a></li>@endif
                    @if(config('site.address'))<li>{{ config('site.address') }}</li>@endif
                    <li><a href="{{ route('site.contact') }}">Send us a message</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright"><span>© {{ now()->year }} acc263. All rights reserved.</span><span>Prices in USD.</span></div>
    </div>
</footer>
@stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Overview') · {{ config('platform.name') }} Platform</title>
    <link rel="icon" href="{{ asset('assets/images/favicon.ico') }}" type="image/x-icon">
    <style>
        :root {
            color-scheme: light;
            --page: #f6f7f9;
            --surface: #ffffff;
            --surface-2: #f3f5f8;
            --border: rgba(11, 11, 11, 0.09);
            --text: #0f172a;
            --text-2: #52514e;
            --muted: #6b7280;
            --accent: #2a78d6;
            --accent-strong: #1c5cab;
            --accent-soft: #e8f1fc;
            --bar-history: #86b6ef;
            --bar-current: #2a78d6;
            --grid: #e7e8ec;
            --axis: #c3c2b7;
            --good: #006300;
            --bad: #d03b3b;
            --warn-bg: #fff7e6;
            --warn-text: #8a5a00;
            --shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            --radius: 14px;
            --font: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        @media (prefers-color-scheme: dark) {
            :root:where(:not([data-theme="light"])) {
                color-scheme: dark;
                --page: #0d0d0d; --surface: #1a1a19; --surface-2: #232321; --border: rgba(255, 255, 255, 0.10);
                --text: #f0efec; --text-2: #c3c2b7; --muted: #9b9a93; --accent: #3987e5; --accent-strong: #6da7ec;
                --accent-soft: rgba(57, 135, 229, 0.16); --bar-history: #184f95; --bar-current: #3987e5;
                --grid: #2c2c2a; --axis: #383835; --good: #0ca30c; --bad: #e66767; --warn-bg: rgba(250, 178, 25, 0.14); --warn-text: #fab219;
                --shadow: none;
            }
        }
        :root[data-theme="dark"] {
            color-scheme: dark;
            --page: #0d0d0d; --surface: #1a1a19; --surface-2: #232321; --border: rgba(255, 255, 255, 0.10);
            --text: #f0efec; --text-2: #c3c2b7; --muted: #9b9a93; --accent: #3987e5; --accent-strong: #6da7ec;
            --accent-soft: rgba(57, 135, 229, 0.16); --bar-history: #184f95; --bar-current: #3987e5;
            --grid: #2c2c2a; --axis: #383835; --good: #0ca30c; --bad: #e66767; --warn-bg: rgba(250, 178, 25, 0.14); --warn-text: #fab219;
            --shadow: none;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { background: var(--page); color: var(--text); font: 15px/1.45 var(--font); -webkit-font-smoothing: antialiased; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .icon { flex: none; }

        .shell { display: grid; grid-template-columns: 248px minmax(0, 1fr); min-height: 100vh; }
        .sidebar { background: var(--surface); border-right: 1px solid var(--border); padding: 28px 20px; display: flex; flex-direction: column; gap: 28px; position: sticky; top: 0; height: 100vh; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 22px; color: var(--accent); letter-spacing: -0.01em; padding: 0 8px; }
        .brand small { display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
        .nav { display: flex; flex-direction: column; gap: 4px; }
        .nav a { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 10px; color: var(--text-2); font-weight: 500; }
        .nav a:hover { background: var(--surface-2); text-decoration: none; color: var(--text); }
        .nav a.active { background: var(--accent); color: #fff; }
        .nav .divider { height: 1px; background: var(--border); margin: 10px 8px; }
        .sidebar-foot { margin-top: auto; display: flex; flex-direction: column; gap: 12px; }
        .plan-card { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; color: var(--text); font-size: 14px; }
        .plan-card small { display: block; color: var(--muted); font-size: 12px; }
        .me { display: flex; align-items: center; gap: 10px; padding: 4px 6px; }
        .me .who { min-width: 0; flex: 1; }
        .me .who strong { display: block; font-size: 14px; }
        .me .who span { display: block; color: var(--muted); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .icon-button { background: none; border: 0; color: var(--muted); cursor: pointer; padding: 6px; border-radius: 8px; display: inline-flex; }
        .icon-button:hover { background: var(--surface-2); color: var(--text); }

        .avatar { width: 40px; height: 40px; border-radius: 50%; display: inline-grid; place-items: center; font-weight: 600; font-size: 14px; flex: none; background: var(--accent-soft); color: var(--accent-strong); }
        .avatar.sm { width: 34px; height: 34px; font-size: 13px; }

        main { padding: 32px 32px 48px; min-width: 0; }
        .page-head { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-head h1 { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.01em; }
        .page-head p { margin: 4px 0 0; color: var(--muted); }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 24px; min-width: 0; }
        .card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
        .card-head h2 { margin: 0; font-size: 17px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-head .sub { color: var(--muted); font-size: 13px; font-weight: 400; }
        .grid { display: grid; gap: 20px; }
        .tile-icon { width: 48px; height: 48px; border-radius: 12px; border: 1px solid var(--border); display: grid; place-items: center; color: var(--accent); flex: none; }
        .count-pill { background: var(--accent-soft); color: var(--accent-strong); font-size: 13px; font-weight: 600; padding: 2px 9px; border-radius: 999px; }
        .muted { color: var(--muted); }
        .info-dot { color: var(--muted); display: inline-flex; cursor: help; }

        .delta { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; font-size: 14px; }
        .delta.good { color: var(--good); } .delta.bad { color: var(--bad); } .delta.flat { color: var(--muted); }
        .delta + .vs { color: var(--muted); font-size: 14px; margin-left: 6px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 10px; padding: 10px 16px; font: 600 14px var(--font); cursor: pointer; border: 1px solid transparent; text-decoration: none !important; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-strong); }
        .btn-outline { background: transparent; border-color: var(--accent); color: var(--accent); }
        .btn-outline:hover { background: var(--accent-soft); }
        .btn-ghost { background: var(--surface); border-color: var(--border); color: var(--text); }
        .btn-ghost:hover { background: var(--surface-2); }
        .btn-danger { background: transparent; border-color: var(--bad); color: var(--bad); }
        .btn-danger:hover { background: rgba(208, 59, 59, 0.08); }

        select, input[type=text], input[type=number], input[type=search], textarea {
            font: 14px var(--font); color: var(--text); background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 9px 12px; min-width: 0; width: 100%;
        }
        select:focus, input:focus, textarea:focus, .btn:focus-visible, a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        label { font-size: 13px; font-weight: 600; color: var(--text-2); display: block; margin-bottom: 6px; }

        table.data { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.data th { text-align: left; font-weight: 600; color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; padding: 10px 12px; border-bottom: 1px solid var(--border); }
        table.data td { padding: 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        table.data tr:last-child td { border-bottom: 0; }
        table.data .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .table-wrap { overflow-x: auto; }

        .badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: var(--surface-2); color: var(--text-2); white-space: nowrap; }
        .badge::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .badge.active { color: var(--good); } .badge.trialing { color: var(--accent-strong); }
        .badge.past_due, .badge.expired { color: var(--warn-text); } .badge.suspended, .badge.failed { color: var(--bad); }
        .badge.cancelled, .badge.void, .badge.refunded { color: var(--muted); } .badge.paid { color: var(--good); } .badge.pending { color: var(--warn-text); }
        .badge.promo { color: #6250d6; }

        .flash { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; gap: 10px; align-items: center; }
        .flash.success { background: rgba(12, 163, 12, 0.10); color: var(--good); }
        .flash.error { background: rgba(208, 59, 59, 0.10); color: var(--bad); }

        .tip { position: fixed; z-index: 50; pointer-events: none; background: var(--text); color: var(--surface); font-size: 13px; padding: 7px 10px; border-radius: 8px; white-space: pre-line; max-width: 260px; box-shadow: 0 6px 20px rgba(0,0,0,.18); opacity: 0; transition: opacity .08s; }
        .tip.show { opacity: 1; }

        details.table-view summary { cursor: pointer; color: var(--muted); font-size: 13px; margin-top: 12px; }

        @media (max-width: 960px) {
            .shell { grid-template-columns: minmax(0, 1fr); }
            .sidebar { position: static; height: auto; flex-direction: row; flex-wrap: wrap; align-items: center; padding: 14px 16px; gap: 12px; min-width: 0; border-right: 0; border-bottom: 1px solid var(--border); }
            .nav { flex-direction: row; flex-wrap: wrap; width: 100%; }
            .nav a { padding: 8px 12px; }
            .nav .divider, .plan-card, .me .who { display: none; }
            .sidebar-foot { position: absolute; top: 14px; right: 16px; margin: 0; }
            .sidebar { position: relative; }
            main { padding: 20px 16px 40px; }
        }
        @yield('styles')
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a href="{{ route('system.platform.dashboard') }}" class="brand" style="text-decoration:none">
            <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><path d="M17 2l13 7.5v15L17 32 4 24.5v-15z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M11.5 17.5l4 4 7-8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>{{ config('platform.name') }}<small>Platform</small></span>
        </a>
        <nav class="nav" aria-label="Platform">
            <a href="{{ route('system.platform.dashboard') }}" class="{{ request()->routeIs('system.platform.*') ? 'active' : '' }}"><x-platform.icon name="home"/> Overview</a>
            <a href="{{ route('system.tenants.index') }}" class="{{ request()->routeIs('system.tenants.*') ? 'active' : '' }}"><x-platform.icon name="layers"/> Tenants & billing</a>
            <a href="{{ route('system.plans.index') }}" class="{{ request()->routeIs('system.plans.*') ? 'active' : '' }}"><x-platform.icon name="bar-chart"/> Plans</a>
            <a href="{{ route('system.coupons.index') }}" class="{{ request()->routeIs('system.coupons.*') ? 'active' : '' }}"><x-platform.icon name="gift"/> Coupons</a>
            <a href="{{ route('system.gateways.index') }}" class="{{ request()->routeIs('system.gateways.*') ? 'active' : '' }}"><x-platform.icon name="card"/> Payment gateways</a>
            <a href="{{ route('system.enquiries.index') }}" class="{{ request()->routeIs('system.enquiries.*') ? 'active' : '' }}"><x-platform.icon name="mail"/> Enquiries</a>
            <a href="{{ route('system.companies.index') }}"><x-platform.icon name="briefcase"/> Companies</a>
            <a href="{{ route('system.users.index') }}"><x-platform.icon name="users"/> Users</a>
            <span class="divider"></span>
            <a href="{{ route('dashboard') }}"><x-platform.icon name="arrow-left"/> Accounting app</a>
        </nav>
        <div class="sidebar-foot">
            <div class="plan-card"><x-platform.icon name="check" class="muted"/><div>Platform administrator<small>Full access to all tenants</small></div></div>
            <div class="me">
                <span class="avatar sm">{{ str(auth()->user()->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') }}</span>
                <div class="who"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Log out" aria-label="Log out"><x-platform.icon name="log-out" size="18"/></button></form>
            </div>
        </div>
    </aside>
    <main>
        @if(session('success'))<div class="flash success" role="status"><x-platform.icon name="check" size="18"/>{{ session('success') }}</div>@endif
        @if(session('error') || $errors->any())<div class="flash error" role="alert"><x-platform.icon name="alert" size="18"/>{{ session('error') ?? $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<div class="tip" id="tip" role="tooltip"></div>
<script>
// Hover/focus tooltip for anything with data-tip (chart marks, info icons).
(function () {
    var tip = document.getElementById('tip'), current = null;
    function place(e, el) {
        var r = el.getBoundingClientRect();
        var x = e && e.clientX ? e.clientX : r.left + r.width / 2, y = e && e.clientY ? e.clientY : r.top;
        var w = tip.offsetWidth, h = tip.offsetHeight;
        tip.style.left = Math.min(window.innerWidth - w - 8, Math.max(8, x - w / 2)) + 'px';
        tip.style.top = Math.max(8, y - h - 14) + 'px';
    }
    function show(e) {
        var el = e.target.closest && e.target.closest('[data-tip]');
        if (!el) return;
        current = el; tip.textContent = el.getAttribute('data-tip'); tip.classList.add('show'); place(e, el);
    }
    function hide(e) {
        var el = e.target.closest && e.target.closest('[data-tip]');
        if (el && el === current) { tip.classList.remove('show'); current = null; }
    }
    document.addEventListener('mouseover', show);
    document.addEventListener('mousemove', function (e) { if (current) place(e, current); });
    document.addEventListener('mouseout', hide);
    document.addEventListener('focusin', show);
    document.addEventListener('focusout', hide);
})();
</script>
</body>
</html>

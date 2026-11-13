<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Employee portal') · acc263</title>
    <link rel="icon" href="{{ asset('assets/images/favicon.ico') }}" type="image/x-icon">
    <style>
        :root { --bg: #f5f7fb; --surface: #fff; --ink: #162033; --muted: #5b6677; --line: #dfe5ee; --accent: #1957c2; --accent-soft: #e8f0fc;
            --good: #067a3a; --good-soft: #e3f4ea; --warn: #8a5a00; --warn-soft: #fff4d6; --bad: #b42318; --bad-soft: #fde8e7; --font: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0d1117; --surface: #161b22; --ink: #e6edf3; --muted: #9aa5b1; --line: #2d333b; --accent: #5b9cf5; --accent-soft: #1b2a41;
            --good: #3fb871; --good-soft: #12301f; --warn: #e3b341; --warn-soft: #3a2e12; --bad: #f47067; --bad-soft: #3d1a19; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.55 var(--font); }
        a { color: var(--accent); }
        .wrap { max-width: 980px; margin: 0 auto; padding: 0 16px; }
        header { background: var(--surface); border-bottom: 1px solid var(--line); }
        header .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 60px; flex-wrap: wrap; }
        .brand { font-weight: 700; color: var(--ink); text-decoration: none; }
        .brand small { display: block; font-weight: 500; color: var(--muted); font-size: 12px; }
        nav { display: flex; gap: 4px; flex-wrap: wrap; }
        nav a { padding: 8px 12px; border-radius: 8px; color: var(--ink); text-decoration: none; font-weight: 500; }
        nav a.active, nav a:hover { background: var(--accent-soft); color: var(--accent); }
        main { padding: 24px 0 48px; }
        h1 { font-size: 24px; margin: 0 0 4px; } h2 { font-size: 17px; margin: 0 0 12px; }
        .muted { color: var(--muted); } .small { font-size: 13px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .stat { font-size: 26px; font-weight: 700; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; } th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        .num { text-align: right; white-space: nowrap; }
        .table-wrap { overflow-x: auto; }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 8px; font: inherit; background: var(--surface); color: var(--ink); }
        .fields { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 8px; border: 1px solid transparent; font: 600 14px var(--font); cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #fff; } .btn-link { background: none; color: var(--accent); padding: 4px 8px; }
        .btn-ghost { background: transparent; border-color: var(--line); color: var(--ink); }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge.pending { background: var(--warn-soft); color: var(--warn); } .badge.approved { background: var(--good-soft); color: var(--good); }
        .badge.rejected { background: var(--bad-soft); color: var(--bad); } .badge.cancelled { background: var(--bg); color: var(--muted); }
        .flash { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .flash.success { background: var(--good-soft); color: var(--good); } .flash.error { background: var(--bad-soft); color: var(--bad); }
        .balances { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
        .balance { padding: 14px; margin: 0; } .balance .stat { font-size: 22px; }
        .bar { height: 8px; background: var(--bg); border-radius: 4px; overflow: hidden; margin: 6px 0; } .bar span { display: block; height: 100%; background: var(--accent); border-radius: 4px; }
        :focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
    </style>
</head>
<body>
@isset($portalEmployee)
    <header>
        <div class="wrap">
            <a href="{{ route('staff.dashboard') }}" class="brand">{{ $portalEmployee->name }}<small>Employee portal</small></a>
            <nav aria-label="Portal">
                @foreach(['staff.dashboard' => 'Home', 'staff.payslips' => 'Payslips', 'staff.leave' => 'Leave', 'staff.overtime' => 'Overtime', 'staff.password.edit' => 'Password'] as $route => $label)
                    <a href="{{ route($route) }}" class="{{ request()->routeIs($route.'*') ? 'active' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <form method="POST" action="{{ route('staff.logout') }}" style="margin:0">@csrf<button class="btn btn-ghost" style="padding:8px 12px">Sign out</button></form>
            </nav>
        </div>
    </header>
@endisset
<main>
    <div class="wrap">
        @if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="flash error" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="flash error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </div>
</main>
</body>
</html>

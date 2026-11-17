{{--
    Sign-in style pages for the self-service portals (employee portal, client portal):
    a brand panel with the portal's message and an illustration, and the form card.
    Sections: title, description, eyebrow, headline, lead, features, illustration, panel_foot, mobile_tagline, card, scripts.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · acc263</title>
    <meta name="description" content="@yield('description')">
    <link rel="icon" href="{{ asset('assets/images/favicon.ico') }}" type="image/x-icon">
    <style>
        :root {
            --blue: #1957c2; --blue-deep: #0f3f91; --teal: #0f766e;
            --bg: #eef2f8; --surface: #ffffff; --ink: #142033; --muted: #5b6677; --line: #d9e0ea; --field: #f7f9fc;
            --danger: #b42318; --danger-soft: #fdecea; --success: #067a3a; --success-soft: #e4f4ea;
            --shadow: 0 24px 60px -24px rgba(16, 38, 82, .35);
            --font: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0b1018; --surface: #121a26; --ink: #e8eef6; --muted: #9aa7b8; --line: #263244; --field: #0f1621;
                --danger: #ff8a80; --danger-soft: #3a1a19; --success: #4ccb85; --success-soft: #11301f; --shadow: 0 24px 60px -24px rgba(0, 0, 0, .7); }
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { margin: 0; font: 15px/1.55 var(--font); color: var(--ink); background: var(--bg); -webkit-font-smoothing: antialiased; }

        .shell { min-height: 100%; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); }

        /* Brand panel */
        .brand-panel { position: relative; overflow: hidden; color: #fff; padding: 56px 64px; display: flex; flex-direction: column; justify-content: space-between;
            background: radial-gradient(1200px 600px at -10% -10%, rgba(255,255,255,.14), transparent 60%), linear-gradient(150deg, var(--blue) 0%, var(--blue-deep) 55%, var(--teal) 120%); }
        .brand-panel::before, .brand-panel::after { content: ""; position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,.12); pointer-events: none; }
        .brand-panel::before { width: 520px; height: 520px; right: -180px; bottom: -160px; }
        .brand-panel::after { width: 760px; height: 760px; right: -300px; bottom: -280px; border-color: rgba(255,255,255,.07); }
        .logo { display: inline-flex; align-items: center; gap: 10px; font-weight: 800; font-size: 22px; letter-spacing: -.01em; color: #fff; text-decoration: none; }
        .hero { position: relative; z-index: 1; max-width: 460px; }
        .eyebrow { display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; opacity: .8; margin-bottom: 14px; }
        .hero h1 { font-size: clamp(30px, 3.4vw, 42px); line-height: 1.12; letter-spacing: -.02em; margin: 0 0 14px; font-weight: 800; }
        .hero p { margin: 0 0 32px; font-size: 17px; opacity: .88; }
        .features { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }
        .features li { display: flex; gap: 14px; align-items: flex-start; }
        .feature-icon { flex: none; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: rgba(255,255,255,.14); backdrop-filter: blur(6px); }
        .features strong { display: block; font-weight: 650; }
        .features span { font-size: 14px; opacity: .8; }
        .panel-foot { position: relative; z-index: 1; font-size: 13px; opacity: .7; }

        /* Illustration card (payslip, invoice) */
        .slip { position: relative; z-index: 1; margin: 34px 0 0; width: min(340px, 100%); background: rgba(255,255,255,.96); color: #142033; border-radius: 16px; padding: 18px 20px;
            box-shadow: 0 30px 60px -20px rgba(0,0,0,.45); transform: rotate(-2.5deg); }
        .slip-head { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #5b6677; margin-bottom: 12px; }
        .slip-head b { color: #142033; font-size: 13px; }
        .slip-row { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px dashed #e2e7ef; color: #5b6677; }
        .slip-row span:last-child { color: #142033; font-variant-numeric: tabular-nums; }
        .slip-net { display: flex; justify-content: space-between; align-items: baseline; margin-top: 10px; font-weight: 700; }
        .slip-net span:last-child { font-size: 20px; color: var(--blue); font-variant-numeric: tabular-nums; }
        .pill { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: #067a3a; background: #e4f4ea; padding: 3px 9px; border-radius: 999px; }

        /* Form side */
        .form-side { display: grid; place-items: center; padding: 48px 24px; }
        .card { width: 100%; max-width: 420px; background: var(--surface); border: 1px solid var(--line); border-radius: 20px; padding: 36px 32px; box-shadow: var(--shadow); }
        .card h2 { margin: 0 0 6px; font-size: 26px; letter-spacing: -.02em; }
        .card .sub { margin: 0 0 26px; color: var(--muted); }
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; }
        .control { position: relative; }
        .control svg.lead { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
        .control input { width: 100%; height: 48px; padding: 0 46px 0 44px; border: 1.5px solid var(--line); border-radius: 12px; background: var(--field); color: var(--ink); font: inherit; transition: border-color .15s, box-shadow .15s, background .15s; }
        .control input::placeholder { color: var(--muted); opacity: .7; }
        .control input:focus { outline: none; border-color: var(--blue); background: var(--surface); box-shadow: 0 0 0 4px rgba(25, 87, 194, .16); }
        .control input[aria-invalid="true"] { border-color: var(--danger); }
        .toggle { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: var(--muted); width: 38px; height: 38px; border-radius: 10px; cursor: pointer; display: grid; place-items: center; }
        .toggle:hover { color: var(--ink); background: var(--field); }
        .toggle:focus-visible, .btn:focus-visible, a:focus-visible { outline: 3px solid rgba(25, 87, 194, .45); outline-offset: 2px; }
        .error-text { color: var(--danger); font-size: 13px; margin-top: 6px; }
        .btn { width: 100%; height: 50px; border: 0; border-radius: 12px; font: 650 16px var(--font); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            background: linear-gradient(135deg, var(--blue), var(--blue-deep)); box-shadow: 0 10px 24px -10px rgba(25, 87, 194, .7); transition: transform .12s, box-shadow .12s, filter .12s; }
        .btn:hover { filter: brightness(1.06); box-shadow: 0 14px 28px -12px rgba(25, 87, 194, .8); }
        .btn:active { transform: translateY(1px); }
        .btn[disabled] { opacity: .75; cursor: progress; }
        .alert { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; font-size: 14px; margin-bottom: 20px; }
        .alert.error { background: var(--danger-soft); color: var(--danger); }
        .alert.success { background: var(--success-soft); color: var(--success); }
        .help { margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--line); font-size: 14px; color: var(--muted); display: grid; gap: 10px; }
        .help div { display: flex; gap: 10px; align-items: flex-start; }
        .help svg { flex: none; margin-top: 2px; color: var(--teal); }
        .secure { margin-top: 18px; text-align: center; font-size: 12px; color: var(--muted); display: flex; gap: 6px; align-items: center; justify-content: center; }
        .mobile-brand { display: none; }

        @media (max-width: 920px) {
            .shell { grid-template-columns: minmax(0, 1fr); }
            .brand-panel { display: none; }
            .form-side { padding: 0 16px 40px; place-items: start center; }
            .mobile-brand { display: block; width: calc(100% + 32px); margin: 0 -16px 24px; padding: 28px 20px 64px; color: #fff;
                background: linear-gradient(150deg, var(--blue) 0%, var(--blue-deep) 60%, var(--teal) 130%); }
            .mobile-brand p { margin: 14px 0 0; opacity: .88; font-size: 15px; }
            .card { margin-top: -52px; padding: 28px 22px; border-radius: 18px; }
            .card h2 { font-size: 23px; }
        }
        .password-hints { margin: -6px 0 18px; padding: 0; list-style: none; display: grid; gap: 4px; font-size: 13px; color: var(--muted); }
        .password-hints li { display: flex; gap: 8px; align-items: center; }
        .password-hints li::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--line); flex: none; }
        .password-hints li.ok { color: var(--success); }
        .password-hints li.ok::before { background: var(--success); }
        /* The illustration needs room; on shorter screens the panel shows the message only. */
        @media (max-height: 900px) { .slip { display: none; } }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } .slip { transform: none; } }
    </style>
</head>
<body>
<div class="shell">
    <aside class="brand-panel" aria-hidden="true">
        <a href="{{ route('site.home') }}" class="logo" tabindex="-1">
            <svg width="32" height="32" viewBox="0 0 34 34"><path d="M17 2l13 7.5v15L17 32 4 24.5v-15z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M11.5 17.5l4 4 7-8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            acc263
        </a>
        <div class="hero">
            <span class="eyebrow">@yield('eyebrow')</span>
            <h1>@yield('headline')</h1>
            <p>@yield('lead')</p>
            <ul class="features">@yield('features')</ul>
            @yield('illustration')
        </div>
        <div class="panel-foot">© {{ now()->year }} acc263 · @yield('panel_foot')</div>
    </aside>

    <main class="form-side">
        <div class="mobile-brand">
            <a href="{{ route('site.home') }}" class="logo">
                <svg width="28" height="28" viewBox="0 0 34 34" aria-hidden="true"><path d="M17 2l13 7.5v15L17 32 4 24.5v-15z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M11.5 17.5l4 4 7-8" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                acc263
            </a>
            <p>@yield('mobile_tagline')</p>
        </div>

        <div class="card">
            @if(session('success'))
                <div class="alert success" role="status"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>{{ session('success') }}</div>
            @endif
            @if(session('error') && !$errors->any())
                <div class="alert error" role="alert">{{ session('error') }}</div>
            @endif
            @yield('card')
        </div>
    </main>
</div>
<script>
(function () {
    // Show / hide password buttons.
    document.querySelectorAll('[data-toggle-password]').forEach(function (toggle) {
        var input = document.getElementById(toggle.dataset.togglePassword);
        toggle.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.querySelector('.eye').style.opacity = show ? '.55' : '1';
            input.focus();
        });
    });
    // Disable the button while submitting, and use the browser's own validation messages.
    document.querySelectorAll('form[data-busy]').forEach(function (form) {
        var submit = form.querySelector('button[type=submit]');
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
            submit.disabled = true; submit.querySelector('span').textContent = form.dataset.busy;
        });
    });
})();
</script>
@stack('scripts')
</body>
</html>

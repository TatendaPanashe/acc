@extends('layouts.authlayout')

@section('title', 'Sign In')

@section('content')
<style>
    :root {
        --acc263-ink: #162033;
        --acc263-muted: #667085;
        --acc263-line: #d9e2ec;
        --acc263-blue: #1957c2;
        --acc263-teal: #0f766e;
        --acc263-gold: #d39b2a;
        --acc263-bg: #f5f7fb;
    }

    body {
        min-height: 100vh;
        margin: 0;
        color: var(--acc263-ink);
        background:
            linear-gradient(135deg, rgba(25, 87, 194, .92), rgba(15, 118, 110, .88)),
            url("{{ asset('assets/images/bg-01.jpg') }}") center/cover fixed;
        font-family: Inter, Roboto, Arial, sans-serif;
    }

    .acc-login {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .acc-login-shell {
        width: min(1080px, 100%);
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        overflow: hidden;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 24px 70px rgba(16, 24, 40, .24);
    }

    .acc-login-panel {
        padding: 48px;
        background: linear-gradient(180deg, #ffffff, var(--acc263-bg));
    }

    .acc-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 46px;
        font-weight: 800;
        font-size: 25px;
        letter-spacing: 0;
    }

    .acc-brand-mark {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        color: #fff;
        background: linear-gradient(135deg, var(--acc263-blue), var(--acc263-teal));
        box-shadow: 0 12px 24px rgba(25, 87, 194, .22);
    }

    .acc-login-panel h1 {
        margin: 0 0 12px;
        font-size: 34px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .acc-login-panel p {
        max-width: 500px;
        color: var(--acc263-muted);
        font-size: 16px;
        line-height: 1.65;
    }

    .acc-proof {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-top: 44px;
    }

    .acc-proof div {
        padding: 18px;
        border: 1px solid var(--acc263-line);
        border-radius: 8px;
        background: rgba(255, 255, 255, .72);
    }

    .acc-proof strong {
        display: block;
        margin-bottom: 6px;
        font-size: 18px;
    }

    .acc-proof span {
        color: var(--acc263-muted);
        font-size: 13px;
    }

    .acc-form-panel {
        padding: 48px;
        background: #fff;
    }

    .acc-form-card h2 {
        margin: 0 0 8px;
        font-size: 24px;
        font-weight: 800;
    }

    .acc-form-card .subtitle {
        margin: 0 0 30px;
        color: var(--acc263-muted);
    }

    .form-label {
        font-weight: 700;
        color: var(--acc263-ink);
    }

    .form-control {
        min-height: 46px;
        border-color: var(--acc263-line);
        border-radius: 8px;
    }

    .form-control:focus {
        border-color: var(--acc263-blue);
        box-shadow: 0 0 0 .2rem rgba(25, 87, 194, .12);
    }

    .acc-submit {
        width: 100%;
        min-height: 48px;
        border: 0;
        border-radius: 8px;
        color: #fff;
        font-weight: 800;
        background: linear-gradient(135deg, var(--acc263-blue), var(--acc263-teal));
    }

    .acc-submit:hover {
        filter: brightness(.97);
    }

    .acc-reset-link {
        color: var(--acc263-blue);
        font-weight: 700;
        text-decoration: none;
    }

    .acc-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin: 18px 0 28px;
    }

    .acc-demo {
        margin-top: 28px;
        padding: 14px;
        border-left: 4px solid var(--acc263-gold);
        border-radius: 8px;
        background: #fff8eb;
        color: #6b4a12;
        font-size: 13px;
        line-height: 1.5;
    }

    @media (max-width: 900px) {
        .acc-login-shell {
            grid-template-columns: 1fr;
        }

        .acc-login-panel {
            padding: 34px;
        }

        .acc-form-panel {
            padding: 34px;
        }
    }

    @media (max-width: 560px) {
        .acc-login {
            padding: 0;
        }

        .acc-login-shell {
            min-height: 100vh;
            border-radius: 0;
        }

        .acc-proof {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="acc-login">
    <section class="acc-login-shell">
        <div class="acc-login-panel">
            <div class="acc-brand">
                <div class="acc-brand-mark"><i class="fas fa-chart-line"></i></div>
                <span>acc263</span>
            </div>

            <h1>Enterprise accounting, ready for control.</h1>
            <p>
                Manage ledgers, receivables, payables, inventory, assets, approvals, and audit trails from one secure finance workspace.
            </p>

            <div class="acc-proof">
                <div><strong>GL</strong><span>Double-entry foundation</span></div>
                <div><strong>AR/AP</strong><span>Invoices and payments</span></div>
                <div><strong>Audit</strong><span>Traceable approvals</span></div>
            </div>
        </div>

        <div class="acc-form-panel">
            <div class="acc-form-card">
                <h2>Sign in</h2>
                <p class="subtitle">Staff can use email or username. Clients can use their customer email.</p>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="login" class="form-label">Email or username</label>
                        <input id="login" type="text" class="form-control @error('login') is-invalid @enderror" name="login" value="{{ old('login') }}" required autocomplete="username" autofocus>
                        @error('login')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                        @error('password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="acc-meta">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        @if (Route::has('password.request'))
                            <a class="acc-reset-link" href="{{ route('password.request') }}">Forgot password?</a>
                        @endif
                    </div>

                    <button type="submit" class="acc-submit">Sign in to acc263</button>
                </form>

                @if (Route::has('register'))
                    <p class="mt-4 mb-0 text-center">
                        New to acc263?
                        <a class="acc-reset-link" href="{{ route('register') }}">Create an account</a>
                    </p>
                @endif

                <div class="acc-demo">
                    Demo admin: finance@example.com / Test123!<br>
                    Superuser: superuser@superuser.com / Test123!
                </div>
            </div>
        </div>
    </section>
</main>
@endsection

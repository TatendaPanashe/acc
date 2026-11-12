@extends('layouts.authlayout')

@section('title', 'Create Account')

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

    .acc-register {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .acc-register-shell {
        width: min(1080px, 100%);
        display: grid;
        grid-template-columns: .95fr 1.05fr;
        overflow: hidden;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 24px 70px rgba(16, 24, 40, .24);
    }

    .acc-register-panel {
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

    .acc-register-panel h1 {
        margin: 0 0 12px;
        font-size: 34px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .acc-register-panel p {
        color: var(--acc263-muted);
        font-size: 16px;
        line-height: 1.65;
    }

    .acc-steps {
        display: grid;
        gap: 14px;
        margin-top: 38px;
    }

    .acc-steps div {
        display: flex;
        gap: 12px;
        padding: 16px;
        border: 1px solid var(--acc263-line);
        border-radius: 8px;
        background: rgba(255, 255, 255, .76);
    }

    .acc-steps i {
        color: var(--acc263-teal);
        margin-top: 3px;
    }

    .acc-steps strong {
        display: block;
        margin-bottom: 3px;
    }

    .acc-steps span {
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

    .acc-link {
        color: var(--acc263-blue);
        font-weight: 700;
        text-decoration: none;
    }

    .acc-note {
        margin-top: 24px;
        padding: 14px;
        border-left: 4px solid var(--acc263-gold);
        border-radius: 8px;
        background: #fff8eb;
        color: #6b4a12;
        font-size: 13px;
        line-height: 1.5;
    }

    @media (max-width: 900px) {
        .acc-register-shell {
            grid-template-columns: 1fr;
        }

        .acc-register-panel,
        .acc-form-panel {
            padding: 34px;
        }
    }

    @media (max-width: 560px) {
        .acc-register {
            padding: 0;
        }

        .acc-register-shell {
            min-height: 100vh;
            border-radius: 0;
        }
    }
</style>

<main class="acc-register">
    <section class="acc-register-shell">
        <div class="acc-register-panel">
            <div class="acc-brand">
                <div class="acc-brand-mark"><i class="fas fa-chart-line"></i></div>
                <span>acc263</span>
            </div>

            <h1>Create your accounting workspace.</h1>
            <p>Your account will create a private company workspace. After signup, you will set the company name, VAT number, and bank accounts before using the system.</p>

            <div class="acc-steps">
                <div>
                    <i class="fas fa-user-plus"></i>
                    <p class="mb-0"><strong>Create account</strong><span>Use your name, email, and password.</span></p>
                </div>
                <div>
                    <i class="fas fa-building"></i>
                    <p class="mb-0"><strong>Set company details</strong><span>Add VAT and banking details for the workspace.</span></p>
                </div>
                <div>
                    <i class="fas fa-lock"></i>
                    <p class="mb-0"><strong>Work privately</strong><span>Accounting records stay scoped to your company.</span></p>
                </div>
            </div>
        </div>

        <div class="acc-form-panel">
            <div class="acc-form-card">
                <h2>Create account</h2>
                <p class="subtitle">Start a new company workspace in acc263.</p>

                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Full name</label>
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                        @error('name')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                        @error('password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password-confirm" class="form-label">Confirm password</label>
                        <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                    </div>

                    @php $signupPlans = \App\Models\Platform\Plan::where('is_active', true)->where('is_custom', false)->orderBy('sort_order')->get(); @endphp
                    @if($signupPlans->isNotEmpty())
                        <div class="mb-4">
                            <label for="plan" class="form-label">Plan for your free 14-day trial</label>
                            <select id="plan" name="plan" class="form-control">
                                @foreach($signupPlans as $signupPlan)
                                    <option value="{{ $signupPlan->code }}" @selected(old('plan', request('plan', config('platform.default_plan'))) === $signupPlan->code)>{{ $signupPlan->name }} · ${{ number_format((float) $signupPlan->monthly_price, 0) }}/month after trial</option>
                                @endforeach
                            </select>
                            <small class="text-muted">No card needed. You can change plan any time.</small>
                        </div>
                    @endif

                    <button type="submit" class="acc-submit">Start free trial</button>
                </form>

                <p class="mt-4 mb-0 text-center">
                    Already have an account?
                    <a class="acc-link" href="{{ route('login') }}">Sign in</a>
                </p>

                <div class="acc-note">
                    After signup, you will be guided through company setup before the dashboard opens.
                </div>
            </div>
        </div>
    </section>
</main>
@endsection

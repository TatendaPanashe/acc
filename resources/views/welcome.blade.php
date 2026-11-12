@extends('layouts.authlayout')

@section('title', 'Welcome')

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

    .acc-welcome {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .acc-welcome-shell {
        width: min(1040px, 100%);
        overflow: hidden;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 24px 70px rgba(16, 24, 40, .24);
    }

    .acc-welcome-main {
        display: grid;
        grid-template-columns: 1.15fr .85fr;
    }

    .acc-welcome-copy {
        padding: 56px;
        background: linear-gradient(180deg, #ffffff, var(--acc263-bg));
    }

    .acc-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 46px;
        font-weight: 800;
        font-size: 25px;
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

    .acc-welcome-copy h1 {
        max-width: 620px;
        margin: 0 0 16px;
        font-size: 42px;
        line-height: 1.12;
        font-weight: 800;
        letter-spacing: 0;
    }

    .acc-welcome-copy p {
        max-width: 620px;
        color: var(--acc263-muted);
        font-size: 17px;
        line-height: 1.65;
    }

    .acc-proof {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-top: 42px;
    }

    .acc-proof div {
        padding: 18px;
        border: 1px solid var(--acc263-line);
        border-radius: 8px;
        background: rgba(255, 255, 255, .74);
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

    .acc-welcome-actions {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 56px;
        background: #fff;
    }

    .acc-welcome-actions h2 {
        margin: 0 0 8px;
        font-size: 25px;
        font-weight: 800;
    }

    .acc-welcome-actions p {
        margin: 0 0 28px;
        color: var(--acc263-muted);
        line-height: 1.6;
    }

    .acc-action {
        display: block;
        width: 100%;
        min-height: 50px;
        padding: 14px 18px;
        border-radius: 8px;
        font-weight: 800;
        text-align: center;
        text-decoration: none;
    }

    .acc-action-primary {
        color: #fff;
        background: linear-gradient(135deg, var(--acc263-blue), var(--acc263-teal));
    }

    .acc-action-secondary {
        margin-top: 12px;
        color: var(--acc263-blue);
        border: 1px solid var(--acc263-line);
        background: #fff;
    }

    @media (max-width: 850px) {
        .acc-welcome-main {
            grid-template-columns: 1fr;
        }

        .acc-welcome-copy,
        .acc-welcome-actions {
            padding: 34px;
        }
    }

    @media (max-width: 560px) {
        .acc-welcome {
            padding: 0;
        }

        .acc-welcome-shell {
            min-height: 100vh;
            border-radius: 0;
        }

        .acc-welcome-copy h1 {
            font-size: 32px;
        }

        .acc-proof {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="acc-welcome">
    <section class="acc-welcome-shell">
        <div class="acc-welcome-main">
            <div class="acc-welcome-copy">
                <div class="acc-brand">
                    <div class="acc-brand-mark"><i class="fas fa-chart-line"></i></div>
                    <span>acc263</span>
                </div>

                <h1>Run each company in its own accounting workspace.</h1>
                <p>Create a workspace, set up company VAT and bank details, then manage ledgers, receivables, payables, inventory, POS, reporting, and audit trails in one place.</p>

                <div class="acc-proof">
                    <div><strong>Tenant</strong><span>Company-isolated data</span></div>
                    <div><strong>VAT</strong><span>Tax-ready records</span></div>
                    <div><strong>Bank</strong><span>Payment accounts</span></div>
                </div>
            </div>

            <div class="acc-welcome-actions">
                <h2>Welcome</h2>
                <p>Sign in to an existing workspace or create a new company account.</p>
                <a class="acc-action acc-action-primary" href="{{ route('register') }}">Create account</a>
                <a class="acc-action acc-action-secondary" href="{{ route('login') }}">Sign in</a>
            </div>
        </div>
    </section>
</main>
@endsection

@extends('layouts.site')

@section('title', 'How we work')
@section('description', 'How acc263 works: sign up, a 14-day free trial, guided setup, roles and approvals, daily accounting, billing and support.')

@section('styles')
    .page-hero { padding: 64px 0 40px; background: linear-gradient(180deg, var(--teal-soft), #fff); }
    .timeline { position: relative; display: grid; gap: 22px; margin-top: 12px; }
    .t-item { display: grid; grid-template-columns: 56px minmax(0, 1fr); gap: 20px; }
    .t-num { width: 48px; height: 48px; border-radius: 50%; background: var(--blue); color: #fff; font-weight: 800; font-size: 18px; display: grid; place-items: center; position: relative; z-index: 1; }
    .t-item:not(:last-child) .t-num::after { content: ""; position: absolute; top: 48px; left: 23px; width: 2px; height: calc(100% + 40px); background: var(--line); z-index: -1; }
    .t-item .card { padding: 22px 24px; }
    .flow { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-top: 28px; }
    .flow .card { position: relative; }
    .flow .card:not(:last-child)::after { content: "→"; position: absolute; right: -14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 20px; }
    .flow small { display: inline-block; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: .06em; font-size: 12px; margin-bottom: 6px; }
    .life { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .faq { max-width: 860px; margin: 0 auto; }
    .faq details { border: 1px solid var(--line); border-radius: 14px; background: #fff; margin-bottom: 12px; }
    .faq summary { cursor: pointer; padding: 18px 22px; font-weight: 600; list-style: none; display: flex; justify-content: space-between; gap: 12px; }
    .faq summary::-webkit-details-marker { display: none; }
    .faq summary::after { content: "+"; font-size: 22px; line-height: 1; color: var(--blue); }
    .faq details[open] summary::after { content: "–"; }
    .faq details p { margin: 0; padding: 0 22px 20px; color: var(--muted); }
    @media (max-width: 980px) { .flow, .life { grid-template-columns: repeat(2, minmax(0, 1fr)); } .flow .card::after { display: none; } }
    @media (max-width: 600px) { .flow, .life { grid-template-columns: minmax(0, 1fr); } .t-item { grid-template-columns: 44px minmax(0, 1fr); gap: 14px; } .t-num { width: 40px; height: 40px; font-size: 16px; } .t-item:not(:last-child) .t-num::after { left: 19px; top: 40px; } }
@endsection

@section('content')
<div class="page-hero">
    <div class="wrap">
        <span class="eyebrow">How we work</span>
        <h1>Simple to start, built to keep you right</h1>
        <p class="lead">Here is exactly what happens from the moment you sign up, how your team works in acc263 day to day, and how billing works.</p>
    </div>
</div>

<section style="padding-top:24px">
    <div class="wrap" style="max-width:900px">
        <h2>Getting started</h2>
        <div class="timeline">
            <div class="t-item"><span class="t-num">1</span><div class="card"><h3>Create your workspace</h3><p>Sign up with your name and email, and choose the plan you want to try. The person who signs up becomes the <strong>Company Admin</strong>.</p></div></div>
            <div class="t-item"><span class="t-num">2</span><div class="card"><h3>Your 14-day free trial starts</h3><p>You get the full features and limits of the plan you chose for 14 days. No card is needed. You can switch plan during the trial.</p></div></div>
            <div class="t-item"><span class="t-num">3</span><div class="card"><h3>Guided setup</h3><p>Four short steps get your books ready:</p>
                <ul class="ticks">
                    <li><strong>Company profile</strong>: name, VAT number, country and bank accounts</li>
                    <li><strong>Currencies</strong>: your base currency and any others you trade in</li>
                    <li><strong>Tax &amp; statutory</strong>: ZIMRA TIN, NSSA number and VAT codes</li>
                    <li><strong>Fiscal year &amp; accounts</strong>: monthly periods and a standard chart of accounts, ready to use</li>
                </ul></div></div>
            <div class="t-item"><span class="t-num">4</span><div class="card"><h3>Invite your team</h3><p>Add colleagues and give each one a role. Your team can sign in as soon as setup is complete.</p></div></div>
            <div class="t-item"><span class="t-num">5</span><div class="card"><h3>Start working</h3><p>Add customers, suppliers and stock, enter opening quantities, and send your first invoice.</p></div></div>
            <div class="t-item"><span class="t-num">6</span><div class="card"><h3>Choose a plan</h3><p>Before the trial ends, the Company Admin chooses a plan and pays online. Everything you entered during the trial stays.</p></div></div>
        </div>
    </div>
</section>

<section class="section-alt">
    <div class="wrap">
        <div class="center"><span class="eyebrow">Day to day</span><h2>Capture, approve, post, report</h2>
            <p class="lead">Work is split so that no single person can both create and approve a transaction, the way good finance teams operate.</p></div>
        <div class="flow">
            <div class="card"><small>Accountant</small><h3>Capture</h3><p>Customers, invoices, receipts, supplier bills, orders and POS sales.</p></div>
            <div class="card"><small>Finance Manager</small><h3>Approve</h3><p>Journals, supplier invoices, payments, credit notes and orders are approved before posting.</p></div>
            <div class="card"><small>acc263</small><h3>Post</h3><p>Every approved document posts to the ledger automatically, in the right period and currency.</p></div>
            <div class="card"><small>Everyone</small><h3>Report</h3><p>Trial balance, P&amp;L, balance sheet and cash flow are always current.</p></div>
        </div>
        <div class="grid g4" style="margin-top:28px">
            <div class="card"><span class="icon-badge"><x-platform.icon name="settings"/></span><h3>Company Admin</h3><p>Company profile, currencies, tax, chart of accounts, team and billing.</p></div>
            <div class="card"><span class="icon-badge teal"><x-platform.icon name="check"/></span><h3>Finance Manager</h3><p>Approves and posts, closes periods, sees all reports.</p></div>
            <div class="card"><span class="icon-badge"><x-platform.icon name="file-text"/></span><h3>Accountant</h3><p>Day-to-day capture. Posts sales invoices and receipts; the rest goes for approval.</p></div>
            <div class="card"><span class="icon-badge teal"><x-platform.icon name="shield"/></span><h3>Auditor</h3><p>Read-only access to every record and report.</p></div>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="center" style="margin-bottom:32px"><span class="eyebrow">Billing</span><h2>Clear, predictable billing</h2></div>
        <div class="life">
            <div class="card"><span class="icon-badge"><x-platform.icon name="card"/></span><h3>Pay the way that suits you</h3><p>Pay online with Paynow (EcoCash, OneMoney, ZimSwitch, Visa), by card, or by bank transfer, monthly or yearly with two months free.</p></div>
            <div class="card"><span class="icon-badge teal"><x-platform.icon name="mail"/></span><h3>Reminders before renewal</h3><p>Your renewal invoice is issued and emailed 7 days before it is due, so there are no surprises.</p></div>
            <div class="card"><span class="icon-badge"><x-platform.icon name="clock"/></span><h3>A grace period if you miss it</h3><p>If a renewal is missed you get 7 days' grace to pay before the workspace is paused. Paying restores access straight away, with all your data.</p></div>
        </div>
    </div>
</section>

<section class="section-alt">
    <div class="wrap">
        <div class="grid g2" style="align-items:center">
            <div><span class="eyebrow">Security &amp; control</span><h2>Built-in safeguards</h2>
                <p class="lead">Accounting controls are part of the product, not an add-on.</p></div>
            <ul class="ticks">
                <li>Each business's data is kept separate from every other business</li>
                <li>Role-based access checked on every action, not just hidden menus</li>
                <li>Closed periods cannot be posted to; posted entries cannot be changed</li>
                <li>Changes recorded with who made them and when</li>
                <li>Payment provider credentials stored encrypted</li>
                <li>Client portal accounts are issued individually, with a forced password change</li>
            </ul>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="center" style="margin-bottom:32px" id="faq"><span class="eyebrow">Questions</span><h2>Frequently asked questions</h2></div>
        <div class="faq">
            <details><summary>Do I need a card to start the trial?</summary><p>No. The 14-day trial is free and needs no card. You only pay when you choose a plan.</p></details>
            <details><summary>What happens when my trial ends?</summary><p>The workspace is paused until the Company Admin chooses a plan. Other users see a notice to contact their admin. Nothing is deleted, and access returns as soon as payment is confirmed.</p></details>
            <details><summary>Can I change my plan later?</summary><p>Yes. The Company Admin can choose a different plan at any time from Billing &amp; Plan. Enterprise plans are arranged with our team.</p></details>
            <details><summary>Which currencies can I use?</summary><p>You choose a base currency (for example USD) and can add others such as ZiG, Rand or Pula on plans that include multi-currency. Exchange rates are kept by date, and exchange gains and losses are posted automatically.</p></details>
            <details><summary>Is acc263 ready for ZIMRA?</summary><p>You can record your TIN and VAT number, use standard, zero-rated and exempt VAT codes, submit invoices to ZIMRA FDMS, and download a statutory report pack.</p></details>
            <details><summary>Can my auditor or external accountant get access?</summary><p>Yes. Add them with the Auditor role on a plan with advanced permissions. Auditors see every record and report but cannot change anything.</p></details>
            <details><summary>Can my customers see their invoices?</summary><p>Yes. You can give each customer access to the client portal, where they can view and download invoices and report payments.</p></details>
            <details><summary>Do I need to install anything?</summary><p>No. acc263 runs in your web browser. A Windows desktop app is also available.</p></details>
            <details><summary>How do I get help?</summary><p>Send us a message from the <a href="{{ route('site.contact') }}">contact page</a>. Business and Enterprise plans include priority and dedicated support.</p></details>
        </div>
    </div>
</section>

<section style="padding-top:0">
    <div class="wrap">
        <div class="cta-band">
            <div><h2>See it for yourself</h2><p>Your 14-day free trial is one form away.</p></div>
            <a href="{{ route('register') }}" class="btn btn-light btn-lg">Start free trial</a>
        </div>
    </div>
</section>
@endsection

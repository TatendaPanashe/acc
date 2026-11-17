@extends('layouts.portal-auth')
@section('title', 'Customer portal')
@section('description', 'Sign in to view your invoices, see what is outstanding and tell us about payments.')
@section('eyebrow', 'Customer portal')
@section('headline', 'Your invoices and account, any time.')
@section('lead', 'See every invoice from your supplier, download fiscal tax invoices, check your balance and let them know when you have paid.')
@section('features')
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></span><div><strong>Invoices</strong><span>Download every invoice as a PDF, with its ZIMRA verification.</span></div></li>
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span><div><strong>Balance</strong><span>See what is paid, what is due and when.</span></div></li>
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg></span><div><strong>Payments</strong><span>Tell us about a payment so it is matched quickly.</span></div></li>
@endsection
@section('illustration')
    <div class="slip">
        <div class="slip-head"><b>Tax invoice · INV-0187</b><span class="pill">✓ Fiscalised</span></div>
        <div class="slip-row"><span>Office printer × 2</span><span>578.00</span></div>
        <div class="slip-row"><span>Copy paper × 10</span><span>245.00</span></div>
        <div class="slip-row"><span>VAT 15%</span><span>123.45</span></div>
        <div class="slip-net"><span>Balance due</span><span>USD 946.45</span></div>
    </div>
@endsection
@section('panel_foot', 'Your account is only visible to you and your supplier.')
@section('mobile_tagline', 'Your invoices, balance and payments, all in one place.')
@section('card')
    <h2>Welcome back</h2>
    <p class="sub">Sign in to your customer portal.</p>

    <form method="POST" action="{{ route('portal.login.submit') }}" data-busy="Signing in…" novalidate>
        @csrf
        <x-portal-auth.email label="Email" placeholder="accounts@yourcompany.co.zw" />
        <x-portal-auth.password />
        <button class="btn" type="submit">
            <span>Sign in</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>

    <div class="help">
        <div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span><strong>First time?</strong> Sign in with the email your supplier has on file and the one-time password they sent you; you will choose your own password next.</span></div>
        <div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg><span><strong>Forgotten your password?</strong> Ask your supplier's accounts team to issue a new one-time password.</span></div>
    </div>
    <div class="secure"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Secure sign-in · only you and your supplier see your account</div>
@endsection

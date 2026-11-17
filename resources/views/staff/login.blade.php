@extends('layouts.portal-auth')
@section('title', 'Employee portal')
@section('description', 'Sign in to see your payslips and request leave or overtime.')
@section('eyebrow', 'Employee portal')
@section('headline', 'Your pay and time off, in one place.')
@section('lead', 'Check your payslips, see your leave balance and send requests to your payroll office, from your phone or computer.')
@section('features')
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></span><div><strong>Payslips</strong><span>Download every approved payslip as a PDF.</span></div></li>
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span><div><strong>Leave</strong><span>Request days off and track what you have left.</span></div></li>
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><div><strong>Overtime</strong><span>Claim extra hours and see when they are paid.</span></div></li>
@endsection
@section('illustration')
    <div class="slip">
        <div class="slip-head"><b>Payslip · June</b><span class="pill">✓ Paid</span></div>
        <div class="slip-row"><span>Basic salary</span><span>1,000.00</span></div>
        <div class="slip-row"><span>Overtime 3 h</span><span>45.00</span></div>
        <div class="slip-row"><span>Tax &amp; NSSA</span><span>−186.20</span></div>
        <div class="slip-net"><span>Net pay</span><span>USD 858.80</span></div>
    </div>
@endsection
@section('panel_foot', 'Your details are only visible to you and your payroll office.')
@section('mobile_tagline', 'Payslips, leave and overtime, all in one place.')
@section('card')
    <h2>Welcome back</h2>
    <p class="sub">Sign in to your employee portal.</p>

    <form method="POST" action="{{ route('staff.login.submit') }}" data-busy="Signing in…" novalidate>
        @csrf
        <x-portal-auth.email label="Work email" />
        <x-portal-auth.password />
        <button class="btn" type="submit">
            <span>Sign in</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>

    <div class="help">
        <div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span><strong>First time?</strong> Use the one-time password from your payroll office; you will choose your own password next.</span></div>
        <div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg><span><strong>Forgotten your password?</strong> Ask your payroll office to issue a new one-time password.</span></div>
    </div>
    <div class="secure"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Secure sign-in · only you and your payroll office see your details</div>
@endsection

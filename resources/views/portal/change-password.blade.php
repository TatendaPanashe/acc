@extends('layouts.portal-auth')
@section('title', 'Choose your password')
@section('description', 'Choose a password for your customer portal.')
@section('eyebrow', 'Customer portal')
@section('headline', 'One last step.')
@section('lead', 'Choose your own password to replace the one-time password you were sent. You will use it every time you sign in.')
@section('features')
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span><div><strong>Private</strong><span>Only you know this password; your supplier cannot see it.</span></div></li>
    <li><span class="feature-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><strong>Strong</strong><span>Use at least 8 characters, ideally a short phrase.</span></div></li>
@endsection
@section('panel_foot', 'Your account is only visible to you and your supplier.')
@section('mobile_tagline', 'Choose a password to finish setting up your account.')
@section('card')
    <h2>Choose your password</h2>
    <p class="sub">Replace your one-time password before you continue to your invoices.</p>

    <form method="POST" action="{{ route('portal.password.update') }}" data-busy="Saving…" novalidate>
        @csrf
        <x-portal-auth.password label="New password" placeholder="At least 8 characters" autocomplete="new-password" :autofocus="true" minlength="8" describedby="password-hints" />
        <ul class="password-hints" id="password-hints" aria-live="polite">
            <li data-rule="length">At least 8 characters</li>
            <li data-rule="match">Both passwords match</li>
        </ul>
        <x-portal-auth.password id="password_confirmation" label="Confirm new password" placeholder="Type it again" autocomplete="new-password" minlength="8" />
        <button class="btn" type="submit">
            <span>Save password and continue</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>
    <div class="secure"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Your password is stored securely and never shown to anyone</div>
@endsection
@push('scripts')
<script>
(function () {
    var pw = document.getElementById('password'), confirm = document.getElementById('password_confirmation');
    var length = document.querySelector('[data-rule=length]'), match = document.querySelector('[data-rule=match]');
    function check() {
        length.classList.toggle('ok', pw.value.length >= 8);
        match.classList.toggle('ok', pw.value.length > 0 && pw.value === confirm.value);
    }
    pw.addEventListener('input', check);
    confirm.addEventListener('input', check);
})();
</script>
@endpush

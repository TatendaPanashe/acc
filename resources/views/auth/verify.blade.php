@extends('layouts.authlayout')

@section('title', 'Confirm your email')

@section('content')
<style>
    body { min-height: 100vh; margin: 0; font-family: Roboto, system-ui, -apple-system, "Segoe UI", sans-serif; color: #162033;
        background: radial-gradient(900px 500px at 0% 0%, rgba(255,255,255,.14), transparent 60%), linear-gradient(150deg, #1957c2 0%, #12408f 55%, #0f766e 120%); }
    .acc-verify { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; box-sizing: border-box; }
    .acc-verify-card { width: 100%; max-width: 480px; background: #fff; border-radius: 14px; padding: 36px 32px; box-sizing: border-box;
        box-shadow: 0 30px 60px -20px rgba(8, 30, 70, .45); text-align: center; }
    .acc-verify-brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 800; font-size: 20px; color: #0f2a47; text-decoration: none; margin-bottom: 26px; }
    .acc-verify-icon { width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center; background: #e8f0fd; color: #1957c2; }
    .acc-verify h1 { font-size: 24px; font-weight: 800; margin: 0 0 10px; }
    .acc-verify p { color: #4b5565; line-height: 1.6; margin: 0 0 14px; font-size: 15px; }
    .acc-verify .email { font-weight: 700; color: #162033; word-break: break-all; }
    .acc-verify-alert { border-radius: 8px; padding: 10px 14px; margin: 0 0 18px; font-size: 14px; text-align: left; }
    .acc-verify-alert.ok { background: #e7f6f3; color: #0b5d56; }
    .acc-verify-alert.err { background: #fdecec; color: #9b1c1c; }
    .acc-verify-btn { width: 100%; min-height: 48px; border: 0; border-radius: 8px; color: #fff; font-weight: 800; font-size: 15px; cursor: pointer;
        background: linear-gradient(135deg, #1957c2, #0f766e); margin-top: 8px; }
    .acc-verify-btn:hover { filter: brightness(.97); }
    .acc-verify-btn:focus-visible, .acc-verify-link:focus-visible { outline: 3px solid rgba(25, 87, 194, .4); outline-offset: 2px; }
    .acc-verify-tips { text-align: left; margin: 22px 0 0; padding: 14px 16px; border-radius: 8px; background: #f5f7fb; color: #4b5565; font-size: 13.5px; line-height: 1.6; }
    .acc-verify-tips ul { margin: 6px 0 0; padding-left: 18px; list-style: disc; }
    .acc-verify-tips li { list-style: disc; }
    .acc-verify-foot { margin-top: 20px; font-size: 14px; color: #4b5565; }
    .acc-verify-link { background: none; border: 0; padding: 0; color: #1957c2; font-weight: 700; cursor: pointer; font-size: 14px; }
    @media (max-width: 480px) { .acc-verify-card { padding: 28px 20px; } }
</style>

<main class="acc-verify">
    <div class="acc-verify-card">
        <a href="{{ route('site.home') }}" class="acc-verify-brand"><x-brand-mark size="36"/> acc263</a>

        <div class="acc-verify-icon" aria-hidden="true">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        </div>

        <h1>Confirm your email</h1>

        @if (session('resent'))
            <div class="acc-verify-alert ok" role="status">We have sent a new confirmation link. It can take a minute to arrive.</div>
        @endif
        @if ($errors->any())
            <div class="acc-verify-alert err" role="alert">{{ $errors->first() }}</div>
        @endif

        <p>We sent a confirmation link to<br><span class="email">{{ auth()->user()->email }}</span></p>
        <p>Click the link in that email to open your workspace. You can't use acc263 until your email is confirmed.</p>

        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="acc-verify-btn">Send the link again</button>
        </form>

        <div class="acc-verify-tips">
            <strong>Can't find the email?</strong>
            <ul>
                <li>Check your spam or junk folder.</li>
                <li>Make sure the address above is correct.</li>
                <li>The link expires after {{ config('auth.verification.expire', 60) }} minutes; send a new one if needed.</li>
            </ul>
        </div>

        <div class="acc-verify-foot">
            Wrong email address?
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="acc-verify-link">Sign out and start again</button>
            </form>
        </div>
    </div>
</main>
@endsection

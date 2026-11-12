@extends('layouts.site')

@section('title', 'Contact us')
@section('description', 'Questions about acc263, plans, a demo or Enterprise? Send us a message.')

@section('styles')
    .page-hero { padding: 64px 0 32px; background: linear-gradient(180deg, var(--blue-soft), #fff); }
    .contact-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 28px; align-items: start; }
    .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .form-grid .full { grid-column: 1 / -1; }
    label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
    input, select, textarea { width: 100%; font: 15px var(--font); color: var(--ink); background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
    textarea { min-height: 160px; resize: vertical; }
    .err { color: #b42318; font-size: 13px; margin-top: 4px; }
    .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    .success { background: var(--teal-soft); color: var(--teal); border-radius: 14px; padding: 18px 20px; font-weight: 600; display: flex; gap: 10px; align-items: center; margin-bottom: 20px; }
    .info-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 18px; }
    .info-list li { display: flex; gap: 14px; align-items: flex-start; }
    .info-list .icon-badge { margin: 0; width: 42px; height: 42px; }
    .info-list strong { display: block; }
    @media (max-width: 900px) { .contact-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 600px) { .form-grid { grid-template-columns: minmax(0, 1fr); } }
@endsection

@section('content')
<div class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Contact</span>
        <h1>Let's talk</h1>
        <p class="lead">Questions about plans, a demo for your team, Enterprise pricing or help getting started: send us a message and we will get back to you by email.</p>
    </div>
</div>

<section style="padding-top:16px">
    <div class="wrap contact-grid">
        <div class="card">
            @if(session('sent'))
                <div class="success" role="status"><x-platform.icon name="check" size="20"/> Thank you. Your message has been sent and we will reply to you by email.</div>
            @endif
            <form method="POST" action="{{ route('site.contact.send') }}" novalidate>
                @csrf
                <div class="form-grid">
                    <div><label for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">@error('name')<div class="err">{{ $message }}</div>@enderror</div>
                    <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email">@error('email')<div class="err">{{ $message }}</div>@enderror</div>
                    <div><label for="company">Business name <span class="muted">(optional)</span></label><input id="company" name="company" value="{{ old('company') }}" maxlength="190" autocomplete="organization"></div>
                    <div><label for="phone">Phone <span class="muted">(optional)</span></label><input id="phone" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></div>
                    <div class="full"><label for="topic">What can we help with?</label>
                        <select id="topic" name="topic">@foreach($topics as $key => $label)<option value="{{ $key }}" @selected(old('topic', $topic) === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="full"><label for="message">Message</label><textarea id="message" name="message" required minlength="10" maxlength="5000" placeholder="Tell us a little about your business and what you need.">{{ old('message') }}</textarea>@error('message')<div class="err">{{ $message }}</div>@enderror</div>
                    <div class="hp" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="full"><button class="btn btn-primary btn-lg">Send message <x-platform.icon name="arrow-right" size="18"/></button></div>
                </div>
            </form>
        </div>
        <aside class="card">
            <h3>Other ways to reach us</h3>
            <ul class="info-list" style="margin-top:18px">
                <li><span class="icon-badge"><x-platform.icon name="mail"/></span><div><strong>Email</strong><a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a></div></li>
                @if(config('site.phone'))<li><span class="icon-badge teal"><x-platform.icon name="phone"/></span><div><strong>Phone</strong><a href="tel:{{ preg_replace('/[^\d+]/', '', config('site.phone')) }}">{{ config('site.phone') }}</a></div></li>@endif
                @if(config('site.whatsapp'))<li><span class="icon-badge teal"><x-platform.icon name="message"/></span><div><strong>WhatsApp</strong><a href="https://wa.me/{{ preg_replace('/\D/', '', config('site.whatsapp')) }}">{{ config('site.whatsapp') }}</a></div></li>@endif
                @if(config('site.address'))<li><span class="icon-badge"><x-platform.icon name="map-pin"/></span><div><strong>Office</strong>{{ config('site.address') }}</div></li>@endif
                <li><span class="icon-badge teal"><x-platform.icon name="clock"/></span><div><strong>Try it now</strong><span class="muted">Start a <a href="{{ route('register') }}">14-day free trial</a> while you wait for our reply.</span></div></li>
            </ul>
            <p class="muted" style="margin-top:20px">Already a customer? <a href="{{ route('login') }}">Sign in</a>. Looking for answers? See the <a href="{{ route('site.how') }}#faq">FAQ</a>.</p>
        </aside>
    </div>
</section>
@endsection

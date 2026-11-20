{{-- "Continue with Google" button and an "or" divider. Always shown; until GOOGLE_CLIENT_ID/SECRET are set,
     the link returns to this page with a "not set up yet" message.
     $intent: 'signin' or 'signup'. On sign-up the chosen plan (#plan select) is carried along. --}}
@php
        $googleQuery = array_filter(['intent' => $intent, 'plan' => $intent === 'signup' ? old('plan', request('plan', config('platform.default_plan'))) : null]);
@endphp
    <style>
        .acc-google { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; min-height: 48px; padding: 0 16px;
            border: 1px solid #d0d7e2; border-radius: 8px; background: #fff; color: #1f2937; font-weight: 700; text-decoration: none; }
        .acc-google:hover, .acc-google:focus { background: #f6f8fb; border-color: #b8c2d1; color: #111827; text-decoration: none; }
        .acc-google:focus-visible { outline: 3px solid rgba(25, 87, 194, .35); outline-offset: 2px; }
        .acc-google svg { flex: none; }
        .acc-or { display: flex; align-items: center; gap: 12px; margin: 20px 0; color: #6b7280; font-size: 13px; }
        .acc-or::before, .acc-or::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; }
    </style>
    <a href="{{ route('auth.google.redirect', $googleQuery) }}" class="acc-google" id="google-auth-link" data-base="{{ route('auth.google.redirect', ['intent' => $intent]) }}">
        <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/>
            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
            <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
            <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/>
        </svg>
        Continue with Google
    </a>
    @if($intent === 'signup')
        <script>
            // Keep the Google link's plan in step with the plan picker below it.
            document.addEventListener('DOMContentLoaded', function () {
                var select = document.getElementById('plan'), link = document.getElementById('google-auth-link');
                if (!select || !link) return;
                select.addEventListener('change', function () {
                    link.href = link.dataset.base + '&plan=' + encodeURIComponent(select.value);
                });
            });
        </script>
    @endif
    <div class="acc-or">or {{ $intent === 'signup' ? 'sign up' : 'sign in' }} with email</div>

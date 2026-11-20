{{-- Live checks for the sign-up form. The server applies the same rules (RegisterController::validator);
     this only shows problems sooner. Fields are checked once touched, and all of them on submit. --}}
<style>
    .acc-pw-rules { list-style: none; margin: 8px 0 0; padding: 0; display: grid; gap: 3px; font-size: 12.5px; color: #667085; }
    .acc-pw-rules li { display: flex; align-items: center; gap: 7px; }
    .acc-pw-rules li::before { content: ""; width: 14px; height: 14px; flex: none; border-radius: 50%; border: 1.5px solid #c3ccd8; }
    .acc-pw-rules li.ok { color: #0f766e; }
    .acc-pw-rules li.ok::before { border-color: #0f766e; background: #0f766e url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M4 8.5l2.5 2.5L12 5.5' fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/12px no-repeat; }
    #register-form .invalid-feedback:empty { display: none !important; }
    #register-form .form-control.is-invalid ~ .invalid-feedback, #register-form .invalid-feedback.d-block { display: block; }
    #register-form .form-control.is-valid { border-color: #0f766e; }
    #register-submit[disabled] { opacity: .7; cursor: progress; }
</style>
<script>
(function () {
    var form = document.getElementById('register-form');
    if (!form) return;

    var f = {
        name: form.querySelector('#name'),
        email: form.querySelector('#email'),
        password: form.querySelector('#password'),
        confirm: form.querySelector('#password-confirm')
    };
    var touched = {};
    var namePattern;
    try { namePattern = new RegExp(f.name.dataset.pattern, 'u'); } catch (e) { namePattern = null; }

    function rules(pw) {
        return { length: pw.length >= 8, 'case': /[a-z]/.test(pw) && /[A-Z]/.test(pw), number: /\d/.test(pw) };
    }

    var checks = {
        name: function () {
            var v = f.name.value.trim().replace(/\s+/g, ' ');
            if (!v) return 'Enter your full name.';
            if (v.length < 2) return 'Your name must have at least 2 characters.';
            if (v.length > 100) return 'Your name can have at most 100 characters.';
            if (namePattern && !namePattern.test(v)) return f.name.dataset.patternMessage;
            return '';
        },
        email: function () {
            var v = f.email.value.trim();
            if (!v) return 'Enter your email address.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || f.email.validity.typeMismatch) return 'Enter a valid email address, like you@company.co.zw.';
            return '';
        },
        password: function () {
            var v = f.password.value, r = rules(v);
            if (!v) return 'Choose a password.';
            if (!r.length || !r['case'] || !r.number) return 'Your password needs at least 8 characters, upper- and lower-case letters and a number.';
            if (v.length > 72) return 'Use a password of at most 72 characters.';
            if (v.toLowerCase() === f.email.value.trim().toLowerCase()) return 'Your password cannot be the same as your email address.';
            return '';
        },
        password_confirmation: function () {
            if (!f.confirm.value) return 'Type your password again.';
            if (f.confirm.value !== f.password.value) return 'The two passwords do not match.';
            return '';
        }
    };
    var inputs = { name: f.name, email: f.email, password: f.password, password_confirmation: f.confirm };

    function show(key) {
        var input = inputs[key], message = checks[key](), box = form.querySelector('[data-for="' + key + '"]');
        input.classList.toggle('is-invalid', !!message);
        input.classList.toggle('is-valid', !message);
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (box) box.textContent = message;
        return !message;
    }

    function paintRules() {
        var r = rules(f.password.value);
        form.querySelectorAll('#password-rules li').forEach(function (li) { li.classList.toggle('ok', !!r[li.dataset.rule]); });
    }

    Object.keys(inputs).forEach(function (key) {
        var input = inputs[key];
        input.addEventListener('blur', function () { if (input.value) touched[key] = true; if (touched[key]) show(key); });
        input.addEventListener('input', function () {
            if (key === 'password') { paintRules(); if (touched.password_confirmation) show('password_confirmation'); }
            if (touched[key]) show(key);
        });
    });
    paintRules();

    form.addEventListener('submit', function (event) {
        var firstBad = null;
        Object.keys(inputs).forEach(function (key) {
            touched[key] = true;
            if (!show(key) && !firstBad) firstBad = inputs[key];
        });
        if (firstBad) {
            event.preventDefault();
            firstBad.focus();
            return;
        }
        var button = document.getElementById('register-submit');
        button.disabled = true;
        button.textContent = 'Creating your workspace…';
    });
})();
</script>

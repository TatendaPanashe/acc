<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password | acc263</title>
    <link href="{{ asset('assets/css/common.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/acc263-uniform.css') }}" rel="stylesheet">
</head>
<body class="acc-app">
    <section class="content" style="margin: 0;">
        <div class="container-fluid" style="max-width: 560px;">
            <div class="block-header">
                <h2>Change Password</h2>
                <p>You must set a new password before accessing your invoices.</p>
            </div>

            <div class="card">
                <div class="header"><h2><strong>Secure</strong> Your Account</h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('portal.password.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="password">New Password</label>
                            <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autofocus>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation">Confirm New Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</body>
</html>

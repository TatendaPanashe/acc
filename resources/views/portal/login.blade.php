<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Login | acc263</title>
    <link href="{{ asset('assets/css/common.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/acc263-uniform.css') }}" rel="stylesheet">
</head>
<body class="acc-app">
    <section class="content" style="margin: 0;">
        <div class="container-fluid" style="max-width: 520px;">
            <div class="block-header">
                <h2>Client Portal</h2>
                <p>Log in with your customer email. New clients use the default password provided by finance.</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card">
                <div class="header"><h2><strong>Client</strong> Login</h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('portal.login.submit') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Log In</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</body>
</html>

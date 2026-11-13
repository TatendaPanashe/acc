@extends('staff.layout')

@section('title', 'Change password')

@section('content')
    <div style="max-width:440px">
        <h1>Change password</h1>
        @if(session('staff_must_change_password'))<p class="muted">You signed in with a one-time password. Choose your own password to continue.</p>@endif
        <div class="card">
            <form method="POST" action="{{ route('staff.password.update') }}">
                @csrf
                <div style="margin-bottom:12px"><label for="current_password">Current (or one-time) password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
                <div style="margin-bottom:12px"><label for="password">New password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"><span class="small muted">At least 8 characters.</span></div>
                <div style="margin-bottom:16px"><label for="password_confirmation">Repeat new password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
                <button class="btn btn-primary">Save password</button>
            </form>
        </div>
    </div>
@endsection

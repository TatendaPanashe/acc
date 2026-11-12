@extends('layouts.main')
@section('title', 'User Management')
@section('content')
<div class="card">
    <div class="header p-3 d-flex justify-content-between align-items-center"><h2><strong>User</strong> Management</h2><a href="{{ route('system.users.create') }}" class="btn btn-primary">Add User</a></div>
    <div class="body p-3">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="table-responsive"><table class="table table-bordered table-striped">
            <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Role</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($users as $user)
                <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->company?->name ?? 'System-wide' }}</td><td>{{ $user->is_superuser ? 'Superuser' : ucwords(str_replace('_', ' ', $user->role)) }}</td><td><a href="{{ route('system.users.edit', $user) }}" class="btn btn-warning btn-sm">Edit</a>@unless($user->is_superuser)<form class="d-inline" method="POST" action="{{ route('system.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form>@endunless</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
</div>
@endsection

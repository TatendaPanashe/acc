@extends('layouts.main')
@section('title', 'Company Management')
@section('content')
<div class="card">
    <div class="header p-3 d-flex justify-content-between align-items-center">
        <h2><strong>Company</strong> Management</h2>
        <a href="{{ route('system.companies.create') }}" class="btn btn-primary">Add Company</a>
    </div>
    <div class="body p-3">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Company</th><th>Registration No.</th><th>Contact</th><th>Users</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($companies as $company)
                    <tr>
                        <td>{{ $company->name }}</td>
                        <td>{{ $company->registration_number ?: '—' }}</td>
                        <td>{{ $company->email ?: $company->phone ?: '—' }}</td>
                        <td>{{ $company->users_count }}</td>
                        <td><span class="badge {{ $company->status ? 'bg-success' : 'bg-secondary' }}">{{ $company->status ? 'Active' : 'Inactive' }}</span></td>
                        <td class="d-flex gap-2">
                            <a href="{{ route('system.companies.edit', $company) }}" class="btn btn-warning btn-sm">Edit</a>
                            @if($company->status)<form method="POST" action="{{ route('system.companies.destroy', $company) }}" onsubmit="return confirm('Deactivate this company?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Deactivate</button></form>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No companies found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

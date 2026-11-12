@extends('layouts.main')
@section('title', 'Edit Customer')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Edit Customer', 'section' => 'Accounts Receivable'])
<div class="card"><div class="header"><h2><strong>{{ $customer->customer_number }}</strong> {{ $customer->name }}</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ar.customers.update', $customer) }}">@include('accounting.ar.customers._form') <a href="{{ route('accounting.ar.customers.index') }}" class="btn btn-secondary">Cancel</a></form></div></div>
<div class="card">
    <div class="header"><h2><strong>Client Portal</strong> Access</h2></div>
    <div class="body">
        @if(session('portal_temporary_password'))
            <div class="alert alert-warning">
                Temporary password for <strong>{{ $customer->email }}</strong>:
                <code class="fs-5 user-select-all">{{ session('portal_temporary_password') }}</code><br>
                This is shown once only. Share it with the customer through a trusted channel; they must change it at first login.
            </div>
        @endif
        <p class="mb-3">
            Status:
            @if(!$customer->portal_password)
                <span class="badge bg-secondary">No access</span>
            @elseif(!$customer->portal_password_changed_at)
                <span class="badge bg-warning text-dark">Temporary password issued</span>
            @else
                <span class="badge bg-success">Active</span>
            @endif
            @if($customer->portal_last_login_at)
                <span class="text-muted ms-2">Last login {{ $customer->portal_last_login_at->diffForHumans() }}</span>
            @endif
        </p>
        <form class="d-inline" method="POST" action="{{ route('accounting.ar.customers.portal-password.issue', $customer) }}"
              onsubmit="return confirm('Issue a new temporary password? Any existing portal password will stop working.')">
            @csrf
            <button class="btn btn-primary" @disabled(!$customer->email)>{{ $customer->portal_password ? 'Reset portal password' : 'Issue portal password' }}</button>
        </form>
        @if($customer->portal_password)
            <form class="d-inline" method="POST" action="{{ route('accounting.ar.customers.portal-password.revoke', $customer) }}"
                  onsubmit="return confirm('Revoke portal access for this customer?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger">Revoke access</button>
            </form>
        @endif
        @if(!$customer->email)
            <p class="text-muted mt-2 mb-0">Add an email address above to enable portal access.</p>
        @endif
    </div>
</div>
@endsection

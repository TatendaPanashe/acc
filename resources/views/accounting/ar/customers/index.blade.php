@extends('layouts.main')

@section('title', 'Customers')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Customers', 'section' => 'Accounts Receivable'])
    <div class="row">
        <div class="col-lg-8"><div class="card">
        <div class="header"><h2><strong>Customer</strong> Master</h2></div>
        <div class="body table-responsive">
            <table class="table table-striped">
                <thead><tr><th>No.</th><th>Name</th><th>Email</th><th>Currency</th><th class="text-end">Credit Limit</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr><td>{{ $customer->customer_number }}</td><td>{{ $customer->name }}</td><td>{{ $customer->email }}</td><td>{{ $customer->currency }}</td><td class="text-end">{{ number_format($customer->credit_limit, 2) }}</td><td class="text-end"><a class="btn btn-primary btn-sm" href="{{ route('accounting.ar.customers.edit', $customer) }}"><i class="fas fa-pen"></i></a><form class="d-inline" method="POST" action="{{ route('accounting.ar.customers.destroy', $customer) }}">@csrf @method('DELETE')<button class="btn btn-warning btn-sm"><i class="fas fa-ban"></i></button></form></td></tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No customers have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $customers->links() }}
        </div>
    </div></div>
    <div class="col-lg-4"><div class="card"><div class="header"><h2><strong>New</strong> Customer</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ar.customers.store') }}">@include('accounting.ar.customers._form')</form></div></div></div>
    </div>
@endsection

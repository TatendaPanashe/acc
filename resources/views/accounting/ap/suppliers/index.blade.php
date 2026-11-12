@extends('layouts.main')

@section('title', 'Suppliers')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Suppliers', 'section' => 'Accounts Payable'])
    <div class="row"><div class="col-lg-8"><div class="card">
        <div class="header"><h2><strong>Supplier</strong> Master</h2></div>
        <div class="body table-responsive">
            <table class="table table-striped">
                <thead><tr><th>No.</th><th>Name</th><th>Email</th><th>Phone</th><th>Currency</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr><td>{{ $supplier->supplier_number }}</td><td>{{ $supplier->name }}</td><td>{{ $supplier->email }}</td><td>{{ $supplier->phone }}</td><td>{{ $supplier->currency }}</td><td class="text-end"><a class="btn btn-primary btn-sm" href="{{ route('accounting.ap.suppliers.edit', $supplier) }}"><i class="fas fa-pen"></i></a><form class="d-inline" method="POST" action="{{ route('accounting.ap.suppliers.destroy', $supplier) }}">@csrf @method('DELETE')<button class="btn btn-warning btn-sm"><i class="fas fa-ban"></i></button></form></td></tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No suppliers have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $suppliers->links() }}
        </div>
    </div></div><div class="col-lg-4"><div class="card"><div class="header"><h2><strong>New</strong> Supplier</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ap.suppliers.store') }}">@include('accounting.ap.suppliers._form')</form></div></div></div></div>
@endsection

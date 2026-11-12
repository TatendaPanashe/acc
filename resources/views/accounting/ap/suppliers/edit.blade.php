@extends('layouts.main')
@section('title', 'Edit Supplier')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Edit Supplier', 'section' => 'Accounts Payable'])
<div class="card"><div class="header"><h2><strong>{{ $supplier->supplier_number }}</strong> {{ $supplier->name }}</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ap.suppliers.update', $supplier) }}">@include('accounting.ap.suppliers._form') <a href="{{ route('accounting.ap.suppliers.index') }}" class="btn btn-secondary">Cancel</a></form></div></div>
@endsection

@extends('layouts.main')
@section('title', 'Edit Supplier Invoice')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Edit Supplier Invoice', 'section' => 'Accounts Payable'])
<div class="card"><div class="header"><h2><strong>{{ $invoice->invoice_number }}</strong></h2></div><div class="body"><form method="POST" action="{{ route('accounting.ap.supplier-invoices.update', $invoice) }}">@include('accounting.ap.invoices._form') <a class="btn btn-secondary" href="{{ route('accounting.ap.supplier-invoices.show', $invoice) }}">Cancel</a></form></div></div>
@endsection

@extends('layouts.main')
@section('title', 'Edit Customer Invoice')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Edit Customer Invoice', 'section' => 'Accounts Receivable'])
<div class="card"><div class="header"><h2><strong>{{ $invoice->invoice_number }}</strong></h2></div><div class="body"><form method="POST" action="{{ route('accounting.ar.invoices.update', $invoice) }}">@include('accounting.ar.invoices._form') <a class="btn btn-secondary" href="{{ route('accounting.ar.invoices.show', $invoice) }}">Cancel</a></form></div></div>
@endsection

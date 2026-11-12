@extends('layouts.main')
@section('title', 'New Supplier Invoice')
@section('content')
@include('accounting.partials.page-header', ['title' => 'New Supplier Invoice', 'section' => 'Accounts Payable'])
<div class="card"><div class="header"><h2><strong>Create</strong> Supplier Invoice</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ap.supplier-invoices.store') }}">@include('accounting.ap.invoices._form')</form></div></div>
@endsection

@extends('layouts.main')
@section('title', 'New Customer Invoice')
@section('content')
@include('accounting.partials.page-header', ['title' => 'New Customer Invoice', 'section' => 'Accounts Receivable'])
<div class="card"><div class="header"><h2><strong>Create</strong> Invoice</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ar.invoices.store') }}">@include('accounting.ar.invoices._form')</form></div></div>
@endsection

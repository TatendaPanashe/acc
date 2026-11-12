@extends('layouts.main')
@section('title', 'Create Sales Order')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Create Sales Order', 'section' => 'Inventory & Order Entry'])
<div class="card"><div class="header"><h2><strong>New</strong> Sales Order</h2></div><div class="body"><form method="POST" action="{{ route('accounting.inventory.sales-orders.store') }}">@include('accounting.inventory.sales_orders._form')</form></div></div>
@endsection

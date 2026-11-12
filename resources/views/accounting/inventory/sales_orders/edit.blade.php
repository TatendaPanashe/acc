@extends('layouts.main')
@section('title', 'Edit Sales Order')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Edit Sales Order', 'section' => 'Inventory & Order Entry'])
<div class="card"><div class="header"><h2><strong>{{ $order->order_number }}</strong></h2></div><div class="body"><form method="POST" action="{{ route('accounting.inventory.sales-orders.update', $order) }}">@method('PATCH') @include('accounting.inventory.sales_orders._form')</form></div></div>
@endsection

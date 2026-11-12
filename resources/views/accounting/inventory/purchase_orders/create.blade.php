@extends('layouts.main')
@section('title', 'Create Purchase Order')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Create Purchase Order', 'section' => 'Inventory & Order Entry'])
<div class="card"><div class="header"><h2><strong>New</strong> Purchase Order</h2></div><div class="body"><form method="POST" action="{{ route('accounting.inventory.purchase-orders.store') }}">@include('accounting.inventory.purchase_orders._form')</form></div></div>
@endsection

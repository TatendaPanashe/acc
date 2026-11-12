@extends('layouts.main')
@section('title', 'Edit Warehouse')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Edit Warehouse', 'section' => 'Inventory & Order Entry'])
<div class="card"><div class="header"><h2><strong>{{ $warehouse->code }}</strong></h2></div><div class="body"><form method="POST" action="{{ route('accounting.inventory.warehouses.update', $warehouse) }}">@csrf @method('PATCH') @include('accounting.inventory.warehouses._form')<button class="btn btn-primary"><i class="fas fa-save"></i> Update</button> <a href="{{ route('accounting.inventory.warehouses.index') }}" class="btn btn-secondary">Cancel</a></form></div></div>
@endsection

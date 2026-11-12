@extends('layouts.main')
@section('title', 'Edit Item')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Edit Item', 'section' => 'Inventory & Order Entry'])
<div class="card"><div class="header"><h2><strong>{{ $item->sku }}</strong></h2></div><div class="body"><form method="POST" action="{{ route('accounting.inventory.items.update', $item) }}">@csrf @method('PATCH') @include('accounting.inventory.items._form') <button class="btn btn-primary"><i class="fas fa-save"></i> Update</button> <a class="btn btn-secondary" href="{{ route('accounting.inventory.items.index') }}">Cancel</a></form></div></div>
@endsection

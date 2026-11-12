@extends('layouts.main')
@section('title', 'Purchase Orders')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Purchase Orders', 'section' => 'Inventory & Order Entry'])
<div class="card"><div class="header d-flex justify-content-between align-items-center"><h2><strong>Purchase Order</strong> Register</h2><a href="{{ route('accounting.inventory.purchase-orders.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Order</a></div><div class="body table-responsive"><table class="table table-striped"><thead><tr><th>No.</th><th>Supplier</th><th>Date</th><th>Expected</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->supplier?->name }}</td><td>{{ $order->order_date?->format('Y-m-d') }}</td><td>{{ $order->expected_date?->format('Y-m-d') }}</td><td>{{ ucfirst($order->status) }}</td><td class="text-end">{{ number_format($order->total, 2) }}</td><td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('accounting.inventory.purchase-orders.show', $order) }}"><i class="fas fa-eye"></i></a>@if($order->status === 'draft') <a class="btn btn-primary btn-sm" href="{{ route('accounting.inventory.purchase-orders.edit', $order) }}"><i class="fas fa-pen"></i></a>@endif</td></tr>@empty<tr><td colspan="7" class="text-muted">No purchase orders created.</td></tr>@endforelse
</tbody></table>{{ $orders->links() }}</div></div>
@endsection

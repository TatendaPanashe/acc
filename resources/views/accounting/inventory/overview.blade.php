@extends('layouts.main')
@section('title', 'Stock Overview')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Stock Overview', 'section' => 'Inventory & Order Entry'])
@if($inventoryAccount)
@php($difference = round($stockValue - (float) $glValue, 2))
<div class="card"><div class="body d-flex flex-wrap gap-4 align-items-center">
    <div><small class="text-muted d-block">Stock ledger value</small><strong>{{ number_format($stockValue, 2) }}</strong></div>
    <div><small class="text-muted d-block">GL {{ $inventoryAccount->code }} {{ $inventoryAccount->name }}</small><strong>{{ number_format((float) $glValue, 2) }}</strong></div>
    <div>@if(abs($difference) < 0.01)<span class="badge bg-success">Stock and GL agree</span>@else<span class="badge bg-warning text-dark">Difference {{ number_format($difference, 2) }}</span> <small class="text-muted">e.g. stock movements recorded before inventory was linked to the ledger</small>@endif</div>
</div></div>
@endif
<div class="row"><div class="col-lg-8"><div class="card"><div class="header"><h2><strong>Stock</strong> Balances</h2></div><div class="body table-responsive"><table class="table table-striped"><thead><tr><th>SKU</th><th>Item</th><th>Warehouse</th><th class="text-end">On Hand</th><th class="text-end">Average Cost</th></tr></thead><tbody>
@forelse($balances as $balance)<tr><td>{{ $balance->item?->sku }}</td><td>{{ $balance->item?->name }}</td><td>{{ $balance->warehouse?->code }}</td><td class="text-end">{{ number_format($balance->quantity_on_hand, 4) }}</td><td class="text-end">{{ number_format($balance->average_cost ?? 0, 2) }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No stock movements have been recorded.</td></tr>@endforelse
</tbody></table></div></div></div><div class="col-lg-4"><div class="card"><div class="header"><h2><strong>Reorder</strong> Alerts</h2></div><div class="body table-responsive"><table class="table"><thead><tr><th>Item</th><th class="text-end">On Hand</th><th class="text-end">Reorder</th></tr></thead><tbody>
@forelse($reorderItems as $item)<tr><td>{{ $item->sku }}</td><td class="text-end">{{ number_format($totalByItem[$item->id] ?? 0, 4) }}</td><td class="text-end">{{ number_format($item->reorder_level, 4) }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No reorder alerts.</td></tr>@endforelse
</tbody></table></div></div></div></div>
@endsection

@extends('layouts.main')

@section('title', 'Items')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Items', 'section' => 'Inventory & Order Entry'])
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="header"><h2><strong>Item</strong> Master</h2></div>
                <div class="body table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>SKU</th><th>Name</th><th>Type</th><th>Costing</th><th class="text-end">Price</th><th class="text-end">On hand</th><th class="text-end">Reorder</th><th></th></tr></thead>
                        <tbody>
                            @forelse($items as $item)
                                <tr>
                                    <td>{{ $item->sku }}</td><td>{{ $item->name }}</td><td>{{ ucfirst($item->type) }}</td><td>{{ ucwords(str_replace('_', ' ', $item->costing_method)) }}</td>
                                    <td class="text-end">{{ number_format($item->selling_price, 2) }}</td>
                                    <td class="text-end">@if($item->type === 'stock')@php($qty = (float) ($onHand[$item->id] ?? 0))<span class="{{ $qty <= (float) $item->reorder_level ? 'text-danger fw-bold' : '' }}">{{ rtrim(rtrim(number_format($qty, 4), '0'), '.') ?: '0' }}</span>@else<span class="text-muted">—</span>@endif</td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format($item->reorder_level, 4), '0'), '.') ?: '0' }}</td>
                                    <td class="text-end"><a class="btn btn-primary btn-sm" href="{{ route('accounting.inventory.items.edit', $item) }}"><i class="fas fa-pen"></i></a><form class="d-inline" method="POST" action="{{ route('accounting.inventory.items.destroy', $item) }}">@csrf @method('DELETE')<button class="btn btn-light btn-sm"><i class="fas fa-ban"></i></button></form></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-muted">No items have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $items->links() }}
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="header"><h2><strong>New</strong> Item</h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('accounting.inventory.items.store') }}">@csrf
                        @include('accounting.inventory.items._form', ['item' => new \App\Modules\Accounting\Models\Item(['type' => 'stock', 'costing_method' => 'weighted_average', 'status' => true]), 'canAddOpeningStock' => true])
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Save Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

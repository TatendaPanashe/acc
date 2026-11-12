@extends('layouts.main')

@section('title', 'POS Sale')

@section('content')
@include('accounting.partials.page-header', ['title' => 'POS Receipt '.$sale->receipt_number, 'section' => 'Point of Sale'])

<div class="card">
    <div class="header d-flex justify-content-between align-items-center">
        <h2><strong>{{ strtoupper($sale->payment_method) }}</strong> {{ $sale->sold_at?->format('Y-m-d H:i') }}</h2>
        <a href="{{ route('accounting.pos.receipt', $sale) }}" class="btn btn-primary"><i class="fas fa-receipt"></i> Thermal Receipt</a>
    </div>
    <div class="body table-responsive">
        <table class="table">
            <thead><tr><th>Item</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Discount</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                @foreach($sale->lines as $line)
                    <tr><td>{{ $line->item?->sku }}</td><td>{{ $line->description }}</td><td class="text-end">{{ number_format($line->quantity, 4) }}</td><td class="text-end">{{ number_format($line->unit_price, 2) }}</td><td class="text-end">{{ number_format($line->discount, 2) }}</td><td class="text-end">{{ number_format($line->tax_amount, 2) }}</td><td class="text-end">{{ number_format($line->line_total + $line->tax_amount, 2) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><th colspan="6" class="text-end">Total</th><th class="text-end">{{ number_format($sale->total, 2) }}</th></tr></tfoot>
        </table>
    </div>
</div>
@endsection

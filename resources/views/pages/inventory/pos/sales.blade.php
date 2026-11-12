@extends('layouts.main')

@section('title')
Welcome
@endsection

@section('content')

 <div class="container">
        <h1>Sale Details ({{ $sale->sale_number }})</h1>

        <div class="card mb-3">
            <div class="card-header">Sale Information</div>
            <div class="card-body">
                <p><strong>Sale Number:</strong> {{ $sale->sale_number }}</p>
                <p><strong>Sale Date:</strong> {{ $sale->sale_date->format('Y-m-d H:i') }}</p>
                <p><strong>Customer:</strong> {{ $sale->customer->name ?? 'Walk-in Customer' }}</p>
                <p><strong>Cashier:</strong> {{ $sale->user->name ?? 'N/A' }}</p>
                <p><strong>Payment Method:</strong> {{ $sale->payment_method }}</p>
                <p><strong>Status:</strong> <span class="badge bg-success">{{ ucfirst($sale->status) }}</span></p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Items Sold</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->saleItems as $item)
                            <tr>
                                <td>{{ $item->product->name ?? 'N/A' }}</td>
                                <td>{{ $item->product->sku ?? 'N/A' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>${{ number_format($item->unit_price, 2) }}</td>
                                <td>${{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">Total Amount:</th>
                            <th class="text-right">${{ number_format($sale->total_amount, 2) }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-right">Amount Paid:</th>
                            <th class="text-right">${{ number_format($sale->paid_amount, 2) }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-right">Change:</th>
                            <th class="text-right">${{ number_format($sale->change_amount, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('sales.index') }}" class="btn btn-secondary">Back to Sales History</a>
            <a href="{{ route('sales.receipt', $sale->id) }}" class="btn btn-primary" target="_blank">Print Receipt</a>
        </div>
    </div>
@endsection

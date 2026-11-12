@extends('layouts.main')

@section('title')
Welcome
@endsection

@section('content')

<div class="container">
        <h1>Sales History</h1>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Total Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Cashier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $sale)
                            <tr>
                                <td>{{ $sale->sale_number }}</td>
                                <td>{{ $sale->sale_date->format('Y-m-d H:i') }}</td>
                                <td>{{ $sale->customer->name ?? 'Walk-in Customer' }}</td>
                                <td>${{ number_format($sale->total_amount, 2) }}</td>
                                <td>{{ $sale->payment_method }}</td>
                                <td><span class="badge bg-success">{{ ucfirst($sale->status) }}</span></td>
                                <td>{{ $sale->user->name ?? 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('sales.show', $sale->id) }}" class="btn btn-sm btn-info">View</a>
                                    <a href="{{ route('sales.receipt', $sale->id) }}" class="btn btn-sm btn-secondary" target="_blank">Print Receipt</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $sales->links() }}
            </div>
        </div>
    </div>
@endsection

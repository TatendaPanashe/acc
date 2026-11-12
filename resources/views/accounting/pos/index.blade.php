@extends('layouts.main')

@section('title', 'POS Sales')

@section('content')
@include('accounting.partials.page-header', ['title' => 'POS Sales Register', 'section' => 'Point of Sale'])

<div class="card">
    <div class="header d-flex justify-content-between align-items-center">
        <h2><strong>Receipt</strong> Register</h2>
        <a href="{{ route('accounting.pos.create') }}" class="btn btn-primary"><i class="fas fa-cash-register"></i> Open Terminal</a>
    </div>
    <div class="body table-responsive">
        <table class="table table-striped">
            <thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th>Store</th><th>Cashier</th><th>Method</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->receipt_number }}</td>
                        <td>{{ $sale->sold_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                        <td>{{ $sale->warehouse?->code }}</td>
                        <td>{{ $sale->cashier?->name }}</td>
                        <td>{{ strtoupper($sale->payment_method) }}</td>
                        <td class="text-end">{{ number_format($sale->total, 2) }}</td>
                        <td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('accounting.pos.show', $sale) }}"><i class="fas fa-eye"></i></a><a class="btn btn-primary btn-sm" href="{{ route('accounting.pos.receipt', $sale) }}"><i class="fas fa-receipt"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No POS sales captured.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $sales->links() }}
    </div>
</div>
@endsection

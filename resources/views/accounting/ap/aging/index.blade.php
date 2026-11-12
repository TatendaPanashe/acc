@extends('layouts.main')

@section('title', 'Payables Aging')

@section('content')
@include('accounting.partials.page-header', ['title' => 'Payables Aging', 'section' => 'Accounts Payable'])

<div class="row">
    @foreach(['current' => 'Current', '1_30' => '1-30', '31_60' => '31-60', '61_90' => '61-90', '90_plus' => '90+'] as $key => $label)
        <div class="col-md">
            <div class="card">
                <div class="body">
                    <small class="text-muted">{{ $label }}</small>
                    <h4 class="mb-0">{{ number_format($buckets[$key], 2) }}</h4>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="header d-flex justify-content-between align-items-center">
        <h2><strong>Open</strong> Supplier Balances</h2>
        <span class="text-muted">As of {{ $asOf->format('Y-m-d') }} | Total {{ number_format($total, 2) }}</span>
    </div>
    <div class="body table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Supplier</th>
                    <th>Due Date</th>
                    <th>Days</th>
                    <th>Priority</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><a href="{{ route('accounting.ap.supplier-invoices.show', $row['invoice']) }}">{{ $row['invoice']->invoice_number }}</a></td>
                        <td>{{ $row['invoice']->supplier?->name }}</td>
                        <td>{{ $row['invoice']->due_date?->format('Y-m-d') }}</td>
                        <td>{{ $row['days_overdue'] }}</td>
                        <td>{{ $row['payment_priority'] }}</td>
                        <td class="text-end">{{ number_format($row['invoice']->total, 2) }}</td>
                        <td class="text-end">{{ number_format($row['invoice']->amount_paid, 2) }}</td>
                        <td class="text-end">{{ number_format($row['invoice']->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No open payables.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@extends('layouts.main')

@section('title', 'Profit & Loss')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Profit & Loss', 'section' => 'Financial Reports'])
    @include('accounting.gl.reports._period_filter', ['route' => route('accounting.gl.profit-and-loss'), 'emptyLabel' => 'Current fiscal year to date'])
    <div class="card">
        <div class="header">
            <h2><strong>Income</strong> Statement: {{ $window['label'] }}</h2>
            <small class="text-muted">{{ $window['from']?->format('d M Y') ?? 'Beginning' }} to {{ $window['to']->format('d M Y') }}</small>
        </div>
        <div class="body table-responsive">
            <table class="table">
                <thead><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <tr><th colspan="2">Revenue</th></tr>
                    @forelse($report['revenue'] as $row)
                        <tr><td>{{ $row->code }} - {{ $row->name }}</td><td class="text-end">{{ number_format($row->balance, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">No revenue posted.</td></tr>
                    @endforelse
                    <tr><th>Total Revenue</th><th class="text-end">{{ number_format($report['revenue_total'], 2) }}</th></tr>
                    <tr><th colspan="2">Expenses</th></tr>
                    @forelse($report['expenses'] as $row)
                        <tr><td>{{ $row->code }} - {{ $row->name }}</td><td class="text-end">{{ number_format($row->balance, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">No expenses posted.</td></tr>
                    @endforelse
                    <tr><th>Total Expenses</th><th class="text-end">{{ number_format($report['expense_total'], 2) }}</th></tr>
                </tbody>
                <tfoot><tr><th>{{ $report['net'] >= 0 ? 'Net Profit' : 'Net Loss' }}</th><th class="text-end">{{ number_format($report['net'], 2) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
@endsection

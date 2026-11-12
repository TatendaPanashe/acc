@extends('layouts.main')

@section('title', 'Balance Sheet')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Balance Sheet', 'section' => 'Financial Reports'])
    @include('accounting.gl.reports._period_filter', ['route' => route('accounting.gl.balance-sheet'), 'emptyLabel' => 'As at today'])
    <div class="card">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><strong>Financial</strong> Position as at {{ $report['as_at']->format('d M Y') }}</h2>
            @if(abs($report['difference']) < 0.005)
                <span class="badge bg-success">Balanced</span>
            @else
                <span class="badge bg-danger">Out of balance by {{ number_format($report['difference'], 2) }}</span>
            @endif
        </div>
        <div class="body table-responsive">
            <table class="table">
                <thead><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <tr><th colspan="2">Assets</th></tr>
                    @forelse($report['assets'] as $row)<tr><td>{{ $row->code }} - {{ $row->name }}</td><td class="text-end">{{ number_format($row->balance, 2) }}</td></tr>@empty<tr><td colspan="2" class="text-muted">None</td></tr>@endforelse
                    <tr class="table-light"><th>Total Assets</th><th class="text-end">{{ number_format($report['asset_total'], 2) }}</th></tr>

                    <tr><th colspan="2">Liabilities</th></tr>
                    @forelse($report['liabilities'] as $row)<tr><td>{{ $row->code }} - {{ $row->name }}</td><td class="text-end">{{ number_format($row->balance, 2) }}</td></tr>@empty<tr><td colspan="2" class="text-muted">None</td></tr>@endforelse
                    <tr class="table-light"><th>Total Liabilities</th><th class="text-end">{{ number_format($report['liability_total'], 2) }}</th></tr>

                    <tr><th colspan="2">Equity</th></tr>
                    @foreach($report['equity'] as $row)<tr><td>{{ $row->code }} - {{ $row->name }}</td><td class="text-end">{{ number_format($row->balance, 2) }}</td></tr>@endforeach
                    <tr><td>Retained earnings from prior years</td><td class="text-end">{{ number_format($report['prior_year_earnings'], 2) }}</td></tr>
                    <tr><td>Current year profit / (loss)</td><td class="text-end">{{ number_format($report['current_year_earnings'], 2) }}</td></tr>
                    <tr class="table-light"><th>Total Equity</th><th class="text-end">{{ number_format($report['equity_total'], 2) }}</th></tr>
                </tbody>
                <tfoot><tr><th>Total Liabilities + Equity</th><th class="text-end">{{ number_format($report['liability_total'] + $report['equity_total'], 2) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', 'Cash Flow')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Cash Flow', 'section' => 'Financial Reports'])
    @include('accounting.gl.reports._period_filter', ['route' => route('accounting.gl.cash-flow'), 'emptyLabel' => 'Current fiscal year to date'])
    <div class="card">
        <div class="header">
            <h2><strong>Cash</strong> Movement: {{ $window['label'] }}</h2>
            <small class="text-muted">Cash & bank accounts: {{ $report['accounts']->map(fn ($a) => $a->code.' '.$a->name)->join(', ') ?: 'none configured' }}</small>
        </div>
        <div class="body table-responsive">
            <table class="table">
                <thead><tr><th>Source</th><th class="text-end">Cash in</th><th class="text-end">Cash out</th><th class="text-end">Net</th></tr></thead>
                <tbody>
                    <tr class="table-light"><th colspan="3">Opening cash ({{ $window['from']?->format('d M Y') ?? 'beginning' }})</th><th class="text-end">{{ number_format($report['opening'], 2) }}</th></tr>
                    @forelse($report['movements'] as $row)
                        <tr>
                            <td>{{ $row['source'] }}</td>
                            <td class="text-end">{{ number_format($row['inflow'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['outflow'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['inflow'] - $row['outflow'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No posted cash movement in this window.</td></tr>
                    @endforelse
                    <tr><th>Net movement</th><th class="text-end">{{ number_format($report['inflow'], 2) }}</th><th class="text-end">{{ number_format($report['outflow'], 2) }}</th><th class="text-end">{{ number_format($report['net'], 2) }}</th></tr>
                </tbody>
                <tfoot><tr><th colspan="3">Closing cash ({{ $window['to']->format('d M Y') }})</th><th class="text-end">{{ number_format($report['closing'], 2) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
@endsection

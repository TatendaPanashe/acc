@extends('layouts.main')

@section('title', 'Cash & collections')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Cash & collections outlook', 'section' => 'AI/ML Automation'])
    @include('accounting.ai._tabs')

    <div class="card">
        <div class="header"><h2><strong>13-week</strong> cash outlook</h2>
            <small class="text-muted">Opening cash {{ number_format($outlook['opening'], 2) }}. Payment dates are learned from {{ $behaviour['ar']['samples'] }} settled customer and {{ $behaviour['ap']['samples'] }} settled supplier invoices
                (typical customer {{ $behaviour['ar']['company'] >= 0 ? $behaviour['ar']['company'].' days late' : abs($behaviour['ar']['company']).' days early' }}).</small></div>
        <div class="body">
            <x-charts.line name="Projected closing cash by week" :labels="collect($outlook['weeks'])->pluck('label')->all()" :series="[['name' => 'Projected cash', 'values' => collect($outlook['weeks'])->pluck('closing')->all()]]"/>
            <div class="table-responsive">
                <table class="table table-sm mt-3">
                    <thead><tr><th>Week</th><th class="text-end">Receipts</th><th class="text-end">Payments</th><th class="text-end">Closing cash</th></tr></thead>
                    <tbody>@foreach($outlook['weeks'] as $week)<tr><td>{{ $week['label'] }}</td><td class="text-end">{{ number_format($week['receipts'], 2) }}</td><td class="text-end">{{ number_format($week['payments'], 2) }}</td><td class="text-end {{ $week['closing'] < 0 ? 'text-danger fw-semibold' : '' }}">{{ number_format($week['closing'], 2) }}</td></tr>@endforeach</tbody>
                </table>
                <small class="text-muted">The last week includes everything expected after it. Recurring contracts not yet invoiced are not included.</small>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach(['receivables' => ['Expected', 'customer receipts', 'Customer'], 'payables' => ['Expected', 'supplier payments', 'Supplier']] as $key => [$a, $b, $partyLabel])
            <div class="col-xl-6">
                <div class="card">
                    <div class="header"><h2><strong>{{ $a }}</strong> {{ $b }}</h2></div>
                    <div class="body table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Invoice</th><th>{{ $partyLabel }}</th><th>Due</th><th>Expected</th><th class="text-end">Balance</th></tr></thead>
                            <tbody>
                            @forelse(${$key}->take(40) as $p)
                                <tr>
                                    <td>{{ $p['invoice']->invoice_number }}</td><td>{{ $p['party'] }}</td><td>{{ $p['due']->format('d M') }}</td>
                                    <td>{{ $p['expected']->format('d M') }}@if($p['late']) <span class="badge bg-danger">overdue</span>@elseif($p['delay'] > 0) <small class="text-muted">+{{ $p['delay'] }}d</small>@endif<small class="d-block text-muted">{{ $p['basis'] }}</small></td>
                                    <td class="text-end">{{ $p['invoice']->currency }} {{ number_format($p['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No open invoices.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

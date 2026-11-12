@extends('layouts.main')

@section('title', 'Reporting & Analytics')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Reporting & Analytics', 'section' => 'Reports'])
    @include('accounting.reporting._tabs')

    @php
        $money = fn ($v) => number_format((float) $v, 0);
        // Delta vs comparison; $goodWhenUp decides the colour, the arrow and sign carry the direction.
        $delta = function ($now, $before, bool $goodWhenUp = true) {
            if ($before === null || (float) $before == 0.0) return null;
            $pct = round(((float) $now - (float) $before) / abs((float) $before) * 100, 1);
            $good = $pct == 0.0 ? null : (($pct > 0) === $goodWhenUp);
            return ['text' => ($pct > 0 ? '▲ +' : ($pct < 0 ? '▼ ' : '')).number_format($pct, 1).'%', 'class' => $good === null ? 'text-muted' : ($good ? 'text-success' : 'text-danger')];
        };
        $tiles = [
            ['Revenue', $money($kpi['revenue']), $delta($kpi['revenue'], $prior['revenue']), null],
            ['Gross profit', $money($kpi['gross_profit']), $delta($kpi['gross_profit'], $prior['gross_profit']), $kpi['gross_margin'] !== null ? $kpi['gross_margin'].'% margin' : null],
            ['Operating expenses', $money($kpi['operating_expenses']), $delta($kpi['operating_expenses'], $prior['operating_expenses'], false), null],
            ['Net profit', $money($kpi['net_profit']), $delta($kpi['net_profit'], $prior['net_profit']), $kpi['net_margin'] !== null ? $kpi['net_margin'].'% margin' : null],
            ['Cash & bank', $money($kpi['cash']), null, 'at '.$to->format('d M Y')],
            ['Receivables', $money($kpi['receivables']), null, $kpi['dso'] !== null ? $kpi['dso'].' days sales outstanding' : null],
            ['Payables', $money($kpi['payables']), null, $kpi['dpo'] !== null ? $kpi['dpo'].' days payables outstanding' : null],
            ['Current ratio', $kpi['current_ratio'] !== null ? number_format($kpi['current_ratio'], 2) : '—', null, 'current assets ÷ current liabilities'],
        ];
    @endphp

    <div class="card">
        <div class="body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">Period</label>
                    <select name="range" class="form-control" onchange="document.getElementById('custom-dates').classList.toggle('d-none', this.value !== 'custom')">
                        @foreach($presets as $key => $text)<option value="{{ $key }}" @selected($filters['range'] === $key)>{{ $text }}</option>@endforeach
                    </select>
                </div>
                <div id="custom-dates" class="col-md-4 row g-2 {{ $filters['range'] === 'custom' ? '' : 'd-none' }}">
                    <div class="col"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? $from->toDateString() }}"></div>
                    <div class="col"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? $to->toDateString() }}"></div>
                </div>
                <div class="col-md-3"><label class="form-label">Compare with</label>
                    <select name="compare" class="form-control">@foreach($compareOptions as $key => $text)<option value="{{ $key }}" @selected($filters['compare'] === $key)>{{ $text }}</option>@endforeach</select>
                </div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Update</button></div>
            </form>
            <small class="text-muted d-block mt-2">{{ $label }} ({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}), compared with {{ strtolower($compareLabel) }}. Amounts in base currency.</small>
        </div>
    </div>

    <div class="row">
        @foreach($tiles as [$title, $value, $change, $hint])
            <div class="col-xl-3 col-sm-6">
                <div class="card"><div class="body">
                    <small class="text-muted d-block mb-1">{{ $title }}</small>
                    <h4 class="mb-1">{{ $value }}</h4>
                    <small>
                        @if($change)<span class="{{ $change['class'] }} fw-semibold">{{ $change['text'] }}</span> <span class="text-muted">vs {{ strtolower($compareLabel) }}</span>@endif
                        @if($hint)<span class="text-muted d-block">{{ $hint }}</span>@endif
                    </small>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header"><h2><strong>Revenue and expenses</strong> by month</h2><small class="text-muted">12 months to {{ $to->format('M Y') }}; expenses include cost of sales</small></div>
                <div class="body"><x-charts.line :labels="$trend['labels']" :series="$trend['series']" name="Revenue and expenses by month"/></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="header"><h2><strong>Receivables</strong> aging</h2><small class="text-muted">open invoices today</small></div>
                <div class="body">
                    <x-charts.bars name="Receivables by days overdue" :width="420" :rows="collect(\App\Modules\Accounting\Services\Reporting\ReportingService::AGING_BUCKETS)->map(fn ($l, $k) => ['label' => $l, 'value' => $aging[$k]])->values()" empty="No open invoices."/>
                    @canroute('accounting.reporting.run')<a class="small" href="{{ route('accounting.reporting.run', 'ar_aging') }}">Open aging report →</a>@endcanroute
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="header"><h2><strong>Top customers</strong> by net sales</h2></div>
                <div class="body"><x-charts.bars name="Top customers by net sales" :rows="$topCustomers" empty="No invoiced sales in this period."/></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card">
                <div class="header"><h2><strong>Largest expenses</strong> by account</h2></div>
                <div class="body"><x-charts.bars name="Largest expenses by account" :rows="$topExpenses" empty="No expenses in this period."/></div>
            </div>
        </div>
    </div>

    @if($saved->isNotEmpty())
        <div class="card">
            <div class="header"><h2><strong>Saved</strong> reports</h2></div>
            <div class="body d-flex flex-wrap gap-2">
                @foreach($saved as $report)<a class="btn btn-outline-primary btn-sm" href="{{ route('accounting.reporting.saved.show', $report) }}">{{ $report->name }}</a>@endforeach
            </div>
        </div>
    @endif
@endsection

@extends('layouts.main')

@section('title', 'AI/ML Automation')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'AI/ML Automation', 'section' => 'Controls'])
    @include('accounting.ai._tabs')
    @include('accounting.partials.metrics', ['metrics' => $metrics])

    <div class="row">
        <div class="col-xl-7">
            <div class="card">
                <div class="header"><h2><strong>Revenue</strong> forecast</h2></div>
                <div class="body">@include('accounting.ai._forecast-chart', ['chart' => $chart])</div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="header"><h2><strong>Cash</strong> outlook</h2><small class="text-muted">13 weeks, from predicted payment dates</small></div>
                <div class="body">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Cash &amp; bank today</span><strong>{{ number_format($outlook['opening'], 2) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Expected receipts</span><strong>{{ number_format(collect($outlook['weeks'])->sum('receipts'), 2) }}</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Expected supplier payments</span><strong>{{ number_format(collect($outlook['weeks'])->sum('payments'), 2) }}</strong></div>
                    <div class="d-flex justify-content-between border-top pt-2"><span class="text-muted">Lowest projected balance</span><strong class="{{ $outlook['lowest'] < 0 ? 'text-danger' : '' }}">{{ $outlook['lowest'] < 0 ? '▼ ' : '' }}{{ number_format($outlook['lowest'], 2) }}</strong></div>
                    @if($outlook['lowest'] < 0)<small class="text-danger d-block mt-1">Cash is projected to go negative: chase collections or reschedule payments.</small>@endif
                    <a class="small d-block mt-2" href="{{ route('accounting.ai-automation.collections') }}">Week-by-week and per-invoice predictions →</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center"><h2><strong>Findings</strong> to review</h2>
            @canroute('accounting.ai-automation.insights.run', 'POST')<form method="POST" action="{{ route('accounting.ai-automation.insights.run') }}">@csrf<button class="btn btn-sm btn-outline-primary">Scan transactions now</button></form>@endcanroute</div>
        <div class="body table-responsive">
            <table class="table align-middle mb-0">
                <tbody>
                @forelse($insights as $insight)
                    @include('accounting.ai._insight-row', ['insight' => $insight])
                @empty
                    <tr><td class="text-muted">No open findings. Scans run daily; you can also scan now.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted">All models run inside acc263 on your own ledger data; nothing is sent to an outside AI service. Every suggestion and finding needs a person's decision.</p>
@endsection

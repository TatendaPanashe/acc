@if($chart['months'] < 4)
    <p class="text-muted mb-0">Forecasting needs at least 4 complete months of posted transactions ({{ $chart['months'] }} so far).</p>
@else
    <x-charts.line :labels="$chart['labels']" :series="$chart['series']" name="Actual and forecast by month"/>
    <small class="text-muted d-block mt-1">
        @if($chart['has_forecast'])
            {{ $chart['method'] === 'holt_winters' ? 'Holt-Winters (trend + monthly seasonality)' : 'Holt linear trend (damped)' }} · confidence {{ number_format((float) $chart['confidence'], 1) }}% · {{ $chart['status'] === 'approved' ? 'approved as plan' : 'awaiting review' }}
        @else
            No forecast yet. Generate one from the Forecasts tab.
        @endif
    </small>
@endif

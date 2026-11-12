@extends('layouts.main')

@section('title', 'Forecasts')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Forecasts', 'section' => 'AI/ML Automation'])
    @include('accounting.ai._tabs')

    <div class="card">
        <div class="body d-flex justify-content-between flex-wrap gap-2 align-items-end">
            <div class="btn-group" role="group" aria-label="Series">
                @foreach(\App\Modules\Accounting\Services\Ai\ForecastService::TYPES as $key => $label)
                    <a href="{{ route('accounting.ai-automation.forecasts.index', ['type' => $key]) }}" class="btn btn-sm {{ $type === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                @endforeach
            </div>
            @canroute('accounting.ai-automation.forecasts.generate', 'POST')
                <form method="POST" action="{{ route('accounting.ai-automation.forecasts.generate') }}" class="d-flex gap-2 align-items-end">
                    @csrf
                    <div><label class="form-label small mb-0">Months ahead</label><input type="number" name="horizon" min="1" max="12" value="6" class="form-control form-control-sm" style="width:90px"></div>
                    <button class="btn btn-sm btn-primary">Generate forecasts</button>
                </form>
            @endcanroute
        </div>
    </div>

    <div class="card">
        <div class="header"><h2><strong>{{ \App\Modules\Accounting\Services\Ai\ForecastService::TYPES[$type] }}</strong> by month</h2><small class="text-muted">last 12 months actual, then forecast</small></div>
        <div class="body">@include('accounting.ai._forecast-chart', ['chart' => $chart])</div>
    </div>

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center flex-wrap gap-2"><h2><strong>Forecast</strong> detail</h2>
            @if($rows->where('status', 'review')->isNotEmpty())
                @canroute('accounting.ai-automation.forecasts.review', 'POST')
                    <div class="d-flex gap-1">
                        <form method="POST" action="{{ route('accounting.ai-automation.forecasts.review') }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-success">Approve as plan</button></form>
                        <form method="POST" action="{{ route('accounting.ai-automation.forecasts.review') }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="decision" value="rejected"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                    </div>
                @endcanroute
            @endif
        </div>
        <div class="body table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Month</th><th class="text-end">Forecast</th><th class="text-end">Likely range (80%)</th><th class="text-end">Confidence</th><th>Model</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr><td>{{ $row->forecast_date->format('F Y') }}</td><td class="text-end">{{ number_format((float) $row->predicted_value, 2) }}</td>
                        <td class="text-end text-muted">{{ number_format((float) $row->lower_value, 2) }} – {{ number_format((float) $row->upper_value, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $row->confidence, 1) }}%</td>
                        <td><small>{{ $row->model_key === 'holt_winters' ? 'Holt-Winters' : 'Holt linear' }} · MAPE {{ $row->features['mape'] ?? '—' }}% · {{ $row->features['history_months'] ?? '?' }} months</small></td>
                        <td>{{ ucfirst($row->status) }}@if($row->reviewer)<small class="text-muted"> by {{ $row->reviewer->name }}</small>@endif</td></tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No forecasts for this series yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <small class="text-muted">Forecasts are fitted to posted monthly totals. Confidence is 100 minus the model's average percentage error on past months.</small>
        </div>
    </div>
@endsection

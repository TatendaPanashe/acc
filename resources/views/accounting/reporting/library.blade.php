@extends('layouts.main')

@section('title', 'Report library')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Report library', 'section' => 'Reporting & Analytics'])
    @include('accounting.reporting._tabs')

    @if($saved->isNotEmpty())
        <div class="card">
            <div class="header"><h2><strong>Saved</strong> reports</h2></div>
            <div class="body table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Name</th><th>Report</th><th>Saved by</th><th>Last opened</th><th></th></tr></thead>
                    <tbody>
                    @foreach($saved as $report)
                        <tr>
                            <td><a href="{{ route('accounting.reporting.saved.show', $report) }}">{{ $report->name }}</a>@if($report->description)<small class="d-block text-muted">{{ $report->description }}</small>@endif</td>
                            <td>{{ \App\Modules\Accounting\Services\Reporting\ReportingService::REPORTS[$report->type][0] ?? $report->type }}</td>
                            <td>{{ $report->creator?->name ?? '—' }}</td>
                            <td>{{ $report->last_run_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end">
                                @canroute('accounting.reporting.saved.destroy', 'DELETE')
                                    <form method="POST" action="{{ route('accounting.reporting.saved.destroy', $report) }}" onsubmit="return confirm('Remove this saved report?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
                                @endcanroute
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row">
        @foreach($catalog as $group => $reports)
            <div class="col-xl-4 col-md-6">
                <div class="card">
                    <div class="header"><h2><strong>{{ $group }}</strong></h2></div>
                    <div class="body">
                        @foreach($reports as $type => [$title, $description])
                            <div class="mb-3">
                                <a href="{{ route('accounting.reporting.run', $type) }}" class="fw-semibold">{{ $title }}</a>
                                <small class="d-block text-muted">{{ $description }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

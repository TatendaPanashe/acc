@extends('layouts.main')

@section('title', 'Jobs')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Jobs', 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center flex-wrap gap-2"><h2><strong>Jobs</strong> &amp; projects</h2>
            <div class="d-flex gap-2">
                <form method="GET"><select name="status" class="form-control form-control-sm" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(\App\Modules\Accounting\Models\JobCost::STATUSES as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></form>
                @canroute('accounting.bom-job-costing.jobs.create')<a href="{{ route('accounting.bom-job-costing.jobs.create') }}" class="btn btn-sm btn-primary">New job</a>@endcanroute
            </div>
        </div>
        <div class="body table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Job</th><th>Customer</th><th>Billing</th><th class="text-end">Budget</th><th class="text-end">Actual</th><th class="text-end">Invoiced</th><th class="text-end">Margin</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($jobs as $job)
                    @php $margin = (float) $job->invoiced_amount - (float) $job->actual_cost; @endphp
                    <tr>
                        <td><a href="{{ route('accounting.bom-job-costing.jobs.show', $job) }}">{{ $job->job_number }}</a><small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($job->name, 40) }}</small></td>
                        <td>{{ $job->customer?->name ?? '—' }}</td>
                        <td>{{ $job->billing_method === 'cost_plus' ? 'Cost + '.(float) $job->markup_percent.'%' : 'Fixed price' }}</td>
                        <td class="text-end">{{ number_format((float) $job->estimated_cost, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $job->actual_cost, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $job->invoiced_amount, 2) }}</td>
                        <td class="text-end {{ $margin < 0 ? 'text-danger' : '' }}">{{ number_format($margin, 2) }}</td>
                        <td><span class="badge {{ ['planned' => 'bg-info text-dark', 'wip' => 'bg-primary', 'variance_review' => 'bg-danger', 'closed' => 'bg-secondary'][$job->status] ?? 'bg-light text-dark' }}">{{ \App\Modules\Accounting\Models\JobCost::STATUSES[$job->status] ?? $job->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No jobs yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $jobs->links() }}
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', 'BOM & Job Costing')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'BOM & Job Costing', 'section' => 'Operations'])
    @include('accounting.production._tabs')
    @include('accounting.partials.metrics', ['metrics' => $metrics])

    @php $woBadge = ['draft' => 'bg-info text-dark', 'released' => 'bg-primary', 'in_progress' => 'bg-warning text-dark']; @endphp
    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-center"><h2><strong>Open</strong> work orders</h2>
                    @canroute('accounting.bom-job-costing.work-orders.store', 'POST')<a href="{{ route('accounting.bom-job-costing.work-orders.index') }}" class="btn btn-sm btn-primary">New work order</a>@endcanroute</div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Work order</th><th>Making</th><th class="text-end">Qty</th><th>Due</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($workOrders as $order)
                            <tr><td><a href="{{ route('accounting.bom-job-costing.work-orders.show', $order) }}">{{ $order->wo_number }}</a></td><td>{{ $order->item?->sku }} {{ $order->item?->name }}</td><td class="text-end">{{ (float) $order->quantity }}</td><td>{{ $order->due_date?->format('d M Y') ?? '—' }}</td><td><span class="badge {{ $woBadge[$order->status] ?? 'bg-secondary' }}">{{ \App\Modules\Accounting\Models\WorkOrder::STATUSES[$order->status] }}</span></td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No open work orders. Create a BOM first, then a work order to make the item.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-center"><h2><strong>Open</strong> jobs</h2>
                    @canroute('accounting.bom-job-costing.jobs.create')<a href="{{ route('accounting.bom-job-costing.jobs.create') }}" class="btn btn-sm btn-primary">New job</a>@endcanroute</div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Job</th><th>Customer</th><th class="text-end">Budget</th><th class="text-end">Actual</th><th>Budget used</th></tr></thead>
                        <tbody>
                        @forelse($jobs as $job)
                            @php $used = (float) $job->estimated_cost > 0 ? round((float) $job->actual_cost / (float) $job->estimated_cost * 100) : null; @endphp
                            <tr>
                                <td><a href="{{ route('accounting.bom-job-costing.jobs.show', $job) }}">{{ $job->job_number }}</a><small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($job->name, 30) }}</small></td>
                                <td>{{ $job->customer?->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format((float) $job->estimated_cost, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $job->actual_cost, 2) }}</td>
                                <td style="min-width:120px">
                                    @if($used !== null)
                                        <div class="progress" style="height:6px" role="progressbar" aria-label="{{ $used }}% of budget used"><div class="progress-bar {{ $job->status === 'variance_review' ? 'bg-danger' : ($used >= 90 ? 'bg-warning' : 'bg-success') }}" style="width: {{ min(100, $used) }}%"></div></div>
                                        <small class="{{ $job->status === 'variance_review' ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $used }}%{{ $job->status === 'variance_review' ? ' · over budget' : '' }}</small>
                                    @else
                                        <small class="text-muted">No budget</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No open jobs.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

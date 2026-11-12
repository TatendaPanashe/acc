@extends('layouts.main')

@section('title', 'Annuity Billing')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Annuity Billing', 'section' => 'Operations'])
    @include('accounting.annuity._tabs')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @include('accounting.partials.metrics', ['metrics' => $metrics])

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2><strong>Recurring</strong> contracts</h2>
                    <div class="d-flex gap-2">
                        <form method="GET"><select name="status" class="form-control form-control-sm" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(\App\Modules\Accounting\Models\Subscription::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></form>
                        @canroute('accounting.annuity-billing.contracts.create')<a href="{{ route('accounting.annuity-billing.contracts.create') }}" class="btn btn-sm btn-primary">New contract</a>@endcanroute
                    </div>
                </div>
                <div class="body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Contract</th><th>Customer</th><th>Billing</th><th class="text-end">Per cycle</th><th>Next invoice</th><th>Term ends</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($contracts as $contract)
                            <tr>
                                <td><a href="{{ route('accounting.annuity-billing.contracts.show', $contract) }}">{{ $contract->contract_number }}</a><small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($contract->description, 40) }}</small></td>
                                <td>{{ $contract->customer?->name }}</td>
                                <td>{{ \App\Modules\Accounting\Models\Subscription::CYCLE_LABELS[$contract->billing_cycle] ?? $contract->billing_cycle }}</td>
                                <td class="text-end">{{ $contract->currency }} {{ number_format((float) $contract->recurring_amount, 2) }}</td>
                                <td>{{ $contract->next_invoice_on?->format('d M Y') ?? '—' }}@if($contract->status === 'active' && $contract->next_invoice_on?->lte(today())) <span class="badge bg-warning text-dark">Due</span>@endif</td>
                                <td>{{ $contract->ends_on?->format('d M Y') ?? 'Open-ended' }}</td>
                                <td><span class="badge {{ ['active' => 'bg-success', 'paused' => 'bg-secondary', 'draft' => 'bg-info text-dark', 'cancelled' => 'bg-danger', 'expired' => 'bg-dark'][$contract->status] ?? 'bg-light text-dark' }}">{{ \App\Modules\Accounting\Models\Subscription::STATUSES[$contract->status] ?? $contract->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No contracts yet. Create one for each customer you bill on a schedule (support, rentals, licences, retainers).</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    {{ $contracts->links() }}
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="header"><h2><strong>Renewals</strong> &amp; expiries</h2><small class="text-muted">next 60 days</small></div>
                <div class="body">
                    @forelse($renewals as $contract)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div><a href="{{ route('accounting.annuity-billing.contracts.show', $contract) }}">{{ $contract->contract_number }}</a><small class="d-block text-muted">{{ $contract->customer?->name }}</small></div>
                            <div class="text-end"><small class="d-block">{{ $contract->ends_on->format('d M Y') }}</small>
                                <span class="badge {{ $contract->auto_renew ? 'bg-success' : 'bg-warning text-dark' }}">{{ $contract->auto_renew ? 'Renews'.((float) $contract->escalation_percent > 0 ? ' +'.$contract->escalation_percent.'%' : '') : 'Expires' }}</span></div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No terms end in the next 60 days.</p>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <div class="header"><h2><strong>Recent</strong> billing runs</h2></div>
                <div class="body">
                    @forelse($runs as $run)
                        <div class="d-flex justify-content-between border-bottom py-2"><a href="{{ route('accounting.annuity-billing.runs.show', $run) }}">{{ $run->run_number }}</a><span>{{ $run->invoice_count }} inv · {{ number_format((float) $run->total, 2) }}</span></div>
                    @empty
                        <p class="text-muted mb-0">No billing runs yet.</p>
                    @endforelse
                    @canroute('accounting.annuity-billing.runs.preview')<a href="{{ route('accounting.annuity-billing.runs.preview') }}" class="btn btn-outline-primary btn-sm mt-3">Prepare a billing run</a>@endcanroute
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', $contract->contract_number)

@section('content')
    @include('accounting.partials.page-header', ['title' => $contract->contract_number, 'section' => 'Annuity Billing'])
    @include('accounting.annuity._tabs')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @php $S = \App\Modules\Accounting\Models\Subscription::class; @endphp
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div><h2><strong>{{ $contract->customer?->name }}</strong></h2><small class="text-muted">{{ $contract->description }}</small></div>
                    <div class="d-flex gap-1 flex-wrap">
                        @canroute('accounting.annuity-billing.contracts.update', 'PUT')
                            @if(!in_array($contract->status, ['cancelled', 'expired']))<a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.annuity-billing.contracts.edit', $contract) }}">Edit</a>@endif
                            @if($contract->status === 'active')
                                <form method="POST" action="{{ route('accounting.annuity-billing.contracts.pause', $contract) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Pause</button></form>
                            @elseif(in_array($contract->status, ['paused', 'draft']))
                                <form method="POST" action="{{ route('accounting.annuity-billing.contracts.resume', $contract) }}">@csrf<button class="btn btn-sm btn-success">{{ $contract->status === 'draft' ? 'Activate' : 'Resume' }}</button></form>
                            @endif
                            @if(in_array($contract->status, ['active', 'paused', 'draft']))
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancel-contract">Cancel contract</button>
                            @endif
                        @endcanroute
                    </div>
                </div>
                <div class="collapse" id="cancel-contract">
                    <form method="POST" action="{{ route('accounting.annuity-billing.contracts.cancel', $contract) }}" class="d-flex gap-2 px-3 pb-3">@csrf
                        <input name="reason" class="form-control" required maxlength="255" placeholder="Reason (e.g. customer gave notice)"><button class="btn btn-danger">Cancel contract</button>
                    </form>
                </div>
                <div class="body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><small class="text-muted d-block">Status</small><strong>{{ $S::STATUSES[$contract->status] ?? $contract->status }}</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Billing</small><strong>{{ $S::CYCLE_LABELS[$contract->billing_cycle] ?? $contract->billing_cycle }}</strong>, in advance</div>
                        <div class="col-md-3"><small class="text-muted d-block">Per cycle (before tax)</small><strong>{{ $contract->currency }} {{ number_format((float) $contract->recurring_amount, 2) }}</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Monthly value</small><strong>{{ $contract->currency }} {{ number_format($contract->monthlyValue(), 2) }}</strong></div>
                        <div class="col-md-3"><small class="text-muted d-block">Started</small>{{ $contract->starts_on?->format('d M Y') ?? '—' }}</div>
                        <div class="col-md-3"><small class="text-muted d-block">Term ends</small>{{ $contract->ends_on?->format('d M Y') ?? 'Open-ended' }}</div>
                        <div class="col-md-3"><small class="text-muted d-block">At term end</small>{{ $contract->auto_renew ? 'Renews '.($contract->term_months ?: 12).' months'.((float) $contract->escalation_percent > 0 ? ', +'.$contract->escalation_percent.'%' : '') : 'Expires' }}</div>
                        <div class="col-md-3"><small class="text-muted d-block">Next invoice</small>{{ $contract->next_invoice_on?->format('d M Y') ?? '—' }}</div>
                    </div>
                    @if($contract->cancellation_reason)<div class="alert alert-secondary">Cancelled {{ $contract->cancelled_on?->format('d M Y') }}: {{ $contract->cancellation_reason }}</div>@endif
                    <table class="table table-sm">
                        <thead><tr><th>Description</th><th>Revenue account</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Tax %</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>@foreach($contract->lines as $line)<tr><td>{{ $line->description }}</td><td>{{ $line->revenueAccount?->code }} {{ $line->revenueAccount?->name }}</td><td class="text-end">{{ (float) $line->quantity }}</td><td class="text-end">{{ number_format((float) $line->unit_price, 2) }}</td><td class="text-end">{{ (float) $line->tax_rate }}</td><td class="text-end">{{ number_format($line->amount(), 2) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="header"><h2><strong>Invoices</strong></h2></div>
                <div class="body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($invoices as $invoice)
                            <tr><td><a href="{{ route('accounting.ar.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->invoice_date->format('d M Y') }}</td><td>{{ $invoice->due_date->format('d M Y') }}</td><td class="text-end">{{ number_format((float) $invoice->total, 2) }}</td><td class="text-end">{{ number_format($invoice->balance, 2) }}</td><td>{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Not invoiced yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="header"><h2><strong>History</strong></h2></div>
                <div class="body">
                    @foreach($contract->events as $event)
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between"><strong>{{ ucfirst($event->type) }}</strong><small class="text-muted">{{ $event->created_at->format('d M Y H:i') }}</small></div>
                            <small class="text-muted">
                                {{ $event->user?->name ?? 'Scheduled run' }}
                                @foreach($event->details ?? [] as $k => $v) · {{ str_replace('_', ' ', $k) }}: {{ is_scalar($v) ? $v : json_encode($v) }}@endforeach
                            </small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', $job->job_number)

@section('content')
    @include('accounting.partials.page-header', ['title' => $job->job_number.' · '.$job->name, 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    @php
        $revenue = (float) $job->invoiced_amount; $cost = (float) $job->actual_cost;
        $open = $job->isOpen();
    @endphp

    @if($job->status === 'variance_review')
        <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><strong>Over budget.</strong> Actual cost {{ number_format($cost, 2) }} is more than {{ (float) $job->variance_tolerance }}% above the budget of {{ number_format((float) $job->estimated_cost, 2) }}.</span>
            @canroute('accounting.bom-job-costing.jobs.acknowledge', 'POST')<form method="POST" action="{{ route('accounting.bom-job-costing.jobs.acknowledge', $job) }}">@csrf<button class="btn btn-sm btn-light">Acknowledge overrun</button></form>@endcanroute
        </div>
    @endif

    @include('accounting.partials.metrics', ['metrics' => [
        ['Budget', number_format((float) $job->estimated_cost, 2), 'target', 'col-blue'],
        ['Actual cost (WIP)', number_format($cost, 2), 'layers', 'col-orange', (float) $job->estimated_cost > 0 ? round($cost / (float) $job->estimated_cost * 100).'% of budget' : null],
        ['Invoiced', number_format($revenue, 2), 'file-text', 'col-green', $job->billing_method === 'fixed' && (float) $job->contract_value > 0 ? 'of '.number_format((float) $job->contract_value, 2).' contract' : ($job->billing_method === 'cost_plus' ? 'cost + '.(float) $job->markup_percent.'%' : null)],
        ['Margin', number_format($revenue - $cost, 2), 'trending-up', 'col-purple', $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1).'%' : null],
    ]])

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div><h2><strong>Budget</strong> vs actual</h2><small class="text-muted">{{ $job->customer?->name ?? 'Internal job' }} · {{ \App\Modules\Accounting\Models\JobCost::STATUSES[$job->status] ?? $job->status }}{{ $job->due_on ? ' · due '.$job->due_on->format('d M Y') : '' }}</small></div>
                    <div class="d-flex gap-1">
                        @if($open)
                            @canroute('accounting.bom-job-costing.jobs.edit')<a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.bom-job-costing.jobs.edit', $job) }}">Edit</a>@endcanroute
                            @canroute('accounting.bom-job-costing.jobs.close', 'POST')
                                <form method="POST" action="{{ route('accounting.bom-job-costing.jobs.close', $job) }}" onsubmit="return confirm('Close the job and move {{ number_format($cost, 2) }} from WIP to cost of sales?')">@csrf<button class="btn btn-sm btn-outline-dark">Close job</button></form>
                            @endcanroute
                        @endif
                    </div>
                </div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Category</th><th class="text-end">Budget</th><th class="text-end">Actual</th><th class="text-end">Remaining</th><th style="width:28%">Used</th></tr></thead>
                        <tbody>
                        @foreach($summary as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td><td class="text-end">{{ number_format($row['budget'], 2) }}</td><td class="text-end">{{ number_format($row['actual'], 2) }}</td>
                                <td class="text-end {{ $row['variance'] < 0 ? 'text-danger' : '' }}">{{ number_format($row['variance'], 2) }}</td>
                                <td>@if($row['used'] !== null)<div class="progress" style="height:6px" role="progressbar" aria-label="{{ $row['used'] }}% used"><div class="progress-bar {{ $row['used'] > 100 ? 'bg-danger' : ($row['used'] >= 90 ? 'bg-warning' : 'bg-success') }}" style="width: {{ min(100, $row['used']) }}%"></div></div><small class="{{ $row['used'] > 100 ? 'text-danger' : 'text-muted' }}">{{ $row['used'] }}%</small>@else<small class="text-muted">{{ $row['actual'] > 0 ? 'Not budgeted' : '—' }}</small>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="header"><h2><strong>Cost</strong> entries</h2></div>
                <div class="body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th><th>By</th></tr></thead>
                        <tbody>
                        @forelse($job->entries as $entry)
                            <tr><td>{{ $entry->entry_date->format('d M Y') }}</td><td>{{ ucfirst($entry->category) }}</td><td>{{ $entry->description }}@if($entry->item)<small class="d-block text-muted">{{ $entry->item->sku }} from stock</small>@endif @if($entry->creditAccount)<small class="d-block text-muted">Cr {{ $entry->creditAccount->code }} {{ $entry->creditAccount->name }}</small>@endif</td>
                                <td class="text-end">{{ (float) $entry->quantity }}</td><td class="text-end">{{ number_format((float) $entry->unit_cost, 2) }}</td><td class="text-end">{{ number_format((float) $entry->amount, 2) }}</td><td><small>{{ $entry->creator?->name }}</small></td></tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No costs recorded yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            @if($open)
                @canroute('accounting.bom-job-costing.jobs.entries.store', 'POST')
                    <div class="card">
                        <div class="header"><h2><strong>Record</strong> a cost</h2><small class="text-muted">Posted to work in progress immediately</small></div>
                        <div class="body">
                            <form method="POST" action="{{ route('accounting.bom-job-costing.jobs.entries.store', $job) }}" class="row g-2" id="cost-form">
                                @csrf
                                <div class="col-6"><label class="form-label">Category</label><select name="category" class="form-control" id="cost-category">@foreach(\App\Modules\Accounting\Models\JobCost::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach</select></div>
                                <div class="col-6"><label class="form-label">Date</label><input type="date" name="entry_date" class="form-control" required value="{{ old('entry_date', today()->toDateString()) }}"></div>
                                <div class="col-12"><label class="form-label">Description</label><input name="description" class="form-control" required maxlength="255" value="{{ old('description') }}"></div>
                                <div class="col-7" data-for="materials"><label class="form-label">Stock item</label><select name="item_id" class="form-control">@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} {{ $item->name }}</option>@endforeach</select></div>
                                <div class="col-5" data-for="materials"><label class="form-label">From</label><select name="warehouse_id" class="form-control">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach</select></div>
                                <div class="col-6" data-for="materials labour overhead"><label class="form-label" data-label>Quantity / hours</label><input type="number" step="0.0001" min="0.0001" name="quantity" class="form-control" value="{{ old('quantity') }}"></div>
                                <div class="col-6" data-for="labour overhead"><label class="form-label">Rate</label><input type="number" step="0.01" min="0" name="unit_cost" class="form-control" value="{{ old('unit_cost') }}"></div>
                                <div class="col-6" data-for="subcontract other"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}"></div>
                                <div class="col-6" data-for="subcontract other"><label class="form-label">Credit account</label><select name="credit_account_id" class="form-control"><option value="">Accrued costs (default)</option>@foreach($creditAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} {{ $a->name }}</option>@endforeach</select></div>
                                <div class="col-12"><button class="btn btn-primary w-100">Record cost</button></div>
                            </form>
                        </div>
                    </div>
                @endcanroute

                @canroute('accounting.bom-job-costing.jobs.invoice', 'POST')
                    <div class="card">
                        <div class="header"><h2><strong>Invoice</strong> the customer</h2></div>
                        <div class="body">
                            @if(!$job->customer_id)
                                <p class="text-muted mb-0">Link the job to a customer to invoice it.</p>
                            @else
                                @php $suggest = $job->billing_method === 'cost_plus' ? $billable : max(0, (float) $job->contract_value - (float) $job->invoiced_amount); @endphp
                                <form method="POST" action="{{ route('accounting.bom-job-costing.jobs.invoice', $job) }}" class="row g-2">
                                    @csrf
                                    <div class="col-12"><label class="form-label">Description</label><input name="description" class="form-control" required maxlength="255" value="{{ $job->name }}{{ $job->billing_method === 'cost_plus' ? ' (costs to date + '.(float) $job->markup_percent.'%)' : '' }}"></div>
                                    <div class="col-6"><label class="form-label">Amount (before tax)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="{{ $suggest > 0 ? number_format($suggest, 2, '.', '') : '' }}"></div>
                                    <div class="col-3"><label class="form-label">Tax %</label><input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" value="15"></div>
                                    <div class="col-3"><label class="form-label">Currency</label><select name="currency" class="form-control">@foreach($currencies as $c)<option @selected($c === $baseCurrency)>{{ $c }}</option>@endforeach</select></div>
                                    <div class="col-12"><label class="form-label">Revenue account</label><select name="revenue_account_id" class="form-control">@foreach($revenueAccounts as $a)<option value="{{ $a->id }}" @selected($a->id === $defaultRevenue)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></div>
                                    <div class="col-12"><button class="btn btn-outline-primary w-100">Create draft invoice</button>
                                        <small class="text-muted">{{ $job->billing_method === 'cost_plus' ? 'Suggested: un-invoiced cost plus mark-up.' : 'Suggested: contract value not yet invoiced.' }}</small></div>
                                </form>
                            @endif
                        </div>
                    </div>
                @endcanroute
            @else
                <div class="card"><div class="body"><strong>Closed {{ $job->closed_on?->format('d M Y') }}.</strong> <span class="text-muted">{{ number_format($cost, 2) }} was moved from work in progress to cost of sales.</span></div></div>
            @endif

            <div class="card">
                <div class="header"><h2><strong>Invoices</strong></h2></div>
                <div class="body">
                    @forelse($job->invoices as $invoice)
                        <div class="d-flex justify-content-between border-bottom py-1"><a href="{{ route('accounting.ar.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a><span>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }} · {{ $invoice->status }}</span></div>
                    @empty
                        <p class="text-muted mb-0">Not invoiced yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var select = document.getElementById('cost-category');
    if (!select) return;
    function sync() {
        var c = select.value;
        document.querySelectorAll('#cost-form [data-for]').forEach(function (el) {
            var show = el.dataset.for.split(' ').indexOf(c) > -1;
            el.classList.toggle('d-none', !show);
            el.querySelectorAll('input, select').forEach(function (i) { i.disabled = !show; });
        });
        var label = document.querySelector('#cost-form [data-label]');
        if (label) label.textContent = c === 'materials' ? 'Quantity' : 'Hours';
    }
    select.addEventListener('change', sync); sync();
})();
</script>
@endpush

@extends('layouts.main')

@section('title', 'Billing run')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Billing run', 'section' => 'Annuity Billing'])
    @include('accounting.annuity._tabs')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">Invoice everything due up to</label><input type="date" name="date" class="form-control" value="{{ $asOf->toDateString() }}"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary">Preview</button></div>
            </form>
            <small class="text-muted d-block mt-2">Contracts are billed in advance: each invoice covers one billing period and is dated on the run date. Missed periods are caught up. Contracts at the end of their term renew (with any escalation) or expire.</small>
        </div>
    </div>

    <div class="card">
        <div class="header"><h2><strong>Preview</strong> · {{ $preview->sum(fn ($p) => $p['periods']->count()) }} invoices</h2></div>
        <div class="body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Contract</th><th>Customer</th><th>Periods</th><th class="text-end">Net</th><th class="text-end">Tax</th><th>Notes</th></tr></thead>
                <tbody>
                @forelse($preview as $row)
                    <tr>
                        <td><a href="{{ route('accounting.annuity-billing.contracts.show', $row['contract']) }}">{{ $row['contract']->contract_number }}</a></td>
                        <td>{{ $row['contract']->customer?->name }}</td>
                        <td><small>{!! $row['periods']->map(fn ($p) => e($p['start']->format('d M Y').' – '.$p['end']->format('d M Y')))->join('<br>') ?: '—' !!}</small></td>
                        <td class="text-end">{{ $row['contract']->currency }} {{ number_format($row['net'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['tax'], 2) }}</td>
                        <td>
                            @if($row['expires'])<span class="badge bg-dark">Term ended: will expire</span>@endif
                            @if($row['renewal'])<span class="badge bg-success">Renews{{ (float) $row['contract']->escalation_percent > 0 ? ' +'.$row['contract']->escalation_percent.'%' : '' }}</span>@endif
                            @if($row['periods']->count() > 1)<span class="badge bg-warning text-dark">{{ $row['periods']->count() }} periods catch-up</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Nothing is due up to {{ $asOf->format('d M Y') }}.</td></tr>
                @endforelse
                </tbody>
            </table>

            @if($preview->isNotEmpty())
                @canroute('accounting.annuity-billing.runs.store', 'POST')
                    <form method="POST" action="{{ route('accounting.annuity-billing.runs.store') }}" class="d-flex gap-3 align-items-center flex-wrap" onsubmit="return confirm('Raise these invoices?')">
                        @csrf <input type="hidden" name="date" value="{{ $asOf->toDateString() }}">
                        @can('sales.post')<label class="d-flex gap-2 align-items-center mb-0"><input type="checkbox" name="post" value="1"> Post the invoices to the ledger now (otherwise they are saved as drafts)</label>@endcan
                        <button class="btn btn-primary">Run billing</button>
                    </form>
                @endcanroute
            @endif
        </div>
    </div>
@endsection

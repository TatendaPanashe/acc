@extends('layouts.main')

@section('title', $report['title'])

@section('content')
    @include('accounting.partials.page-header', ['title' => $report['title'], 'section' => 'Reporting & Analytics'])
    @include('accounting.reporting._tabs')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="body">
            <form method="GET" class="row g-2 align-items-end">
                @if(in_array('range', $uses))
                    <div class="col-md-2"><label class="form-label">Period</label>
                        <select name="range" class="form-control" onchange="document.getElementById('custom-dates').classList.toggle('d-none', this.value !== 'custom')">
                            @foreach($presets as $key => $text)<option value="{{ $key }}" @selected(($filters['range'] ?? 'ytd') === $key)>{{ $text }}</option>@endforeach
                        </select>
                    </div>
                    <div id="custom-dates" class="col-md-3 row g-2 {{ ($filters['range'] ?? '') === 'custom' ? '' : 'd-none' }}">
                        <div class="col"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}"></div>
                        <div class="col"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}"></div>
                    </div>
                @endif
                @if(in_array('as_at', $uses))
                    <div class="col-md-2"><label class="form-label">As at</label><input type="date" name="as_at" class="form-control" value="{{ $filters['as_at'] ?? now()->toDateString() }}"></div>
                @endif
                @if(in_array('compare', $uses))
                    <div class="col-md-2"><label class="form-label">Compare</label><select name="compare" class="form-control">@foreach($compareOptions as $key => $text)<option value="{{ $key }}" @selected(($filters['compare'] ?? 'none') === $key)>{{ $text }}</option>@endforeach</select></div>
                @endif
                @if(in_array('account', $uses))
                    <div class="col-md-3"><label class="form-label">Account</label><select name="account_id" class="form-control" required><option value="">Choose…</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(($filters['account_id'] ?? null) == $account->id)>{{ $account->code }} {{ $account->name }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-2 d-flex gap-1">
                    @if($uses)<button class="btn btn-primary">Run</button>@endif
                    <a class="btn btn-outline-secondary" href="{{ route('accounting.reporting.run', ['type' => $type, 'format' => 'csv'] + $filters) }}">CSV</a>
                    <a class="btn btn-outline-secondary" target="_blank" href="{{ route('accounting.reporting.run', ['type' => $type, 'print' => 1] + $filters) }}">Print</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="header d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div><h2><strong>{{ $report['title'] }}</strong></h2><small class="text-muted">{{ $report['subtitle'] }} · base currency</small></div>
            @canroute('accounting.reporting.saved.store', 'POST')
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#save-report">Save this report</button>
            @endcanroute
        </div>
        <div class="collapse" id="save-report">
            <form method="POST" action="{{ route('accounting.reporting.saved.store') }}" class="row g-2 px-3 pb-3">
                @csrf <input type="hidden" name="type" value="{{ $type }}">
                @foreach($filters as $key => $value)@if($value !== null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                <div class="col-md-4"><input name="name" class="form-control" placeholder="Name, e.g. Monthly management P&L" required maxlength="120"></div>
                <div class="col-md-6"><input name="description" class="form-control" placeholder="Description (optional)" maxlength="255"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Save</button></div>
            </form>
        </div>
        <div class="body table-responsive">
            @include('accounting.reporting._table', ['report' => $report])
            <small class="text-muted d-block mt-2">Click an account to drill down to its ledger lines, and a ledger line to open the journal.</small>
        </div>
    </div>
@endsection

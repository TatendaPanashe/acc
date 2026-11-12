@extends('layouts.main')

@section('title', 'Depreciation Runs')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Depreciation Runs', 'section' => 'Fixed Assets'])
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="header"><h2><strong>Run</strong> depreciation</h2></div>
        <div class="body">
            <form method="GET" action="{{ route('accounting.fixed-assets.depreciation.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label class="form-label">Period</label>
                    <select name="period_id" class="form-control" onchange="this.form.submit()">
                        @foreach($periods as $period)<option value="{{ $period->id }}" @selected($selected?->id === $period->id)>{{ $period->name }}</option>@endforeach
                    </select>
                </div>
            </form>
            @if(!$selected)
                <p class="text-muted">No open accounting periods.</p>
            @elseif($alreadyRun)
                <div class="alert alert-info mb-0">Depreciation has already been run for {{ $selected->name }}.</div>
            @elseif($preview->isEmpty())
                <p class="text-muted mb-0">No assets need depreciation up to {{ $selected->ends_on->format('d M Y') }}.</p>
            @else
                <table class="table table-sm">
                    <thead><tr><th>Asset</th><th>Method</th><th class="text-end">Months</th><th class="text-end">Charge</th><th class="text-end">NBV after</th></tr></thead>
                    <tbody>
                    @foreach($preview as $row)
                        <tr><td>{{ $row['asset']->asset_number }} {{ $row['asset']->name }}</td><td>{{ str_replace('_', ' ', $row['asset']->depreciation_method) }}</td><td class="text-end">{{ $row['months'] }}</td><td class="text-end">{{ number_format($row['amount'], 2) }}</td><td class="text-end">{{ number_format($row['nbv_after'], 2) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="3">Total to post (Dr Depreciation / Cr Accumulated depreciation)</th><th class="text-end">{{ number_format($preview->sum('amount'), 2) }}</th><th></th></tr></tfoot>
                </table>
                @canroute('accounting.fixed-assets.depreciation.store', 'POST')
                    <form method="POST" action="{{ route('accounting.fixed-assets.depreciation.store') }}" onsubmit="return confirm('Post depreciation for this period?')">
                        @csrf <input type="hidden" name="accounting_period_id" value="{{ $selected->id }}">
                        <button class="btn btn-success">Post depreciation for {{ $selected->name }}</button>
                    </form>
                @endcanroute
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header"><h2><strong>Asset</strong> register</h2></div>
                <div class="body table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Asset</th><th>Acquired</th><th class="text-end">Cost</th><th class="text-end">Accumulated</th><th class="text-end">NBV</th><th>Depreciated to</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($assets as $asset)
                            <tr><td>{{ $asset->asset_number }} {{ $asset->name }}</td><td>{{ $asset->acquired_on->format('Y-m-d') }}</td><td class="text-end">{{ number_format((float) $asset->cost, 2) }}</td><td class="text-end">{{ number_format((float) $asset->accumulated_depreciation, 2) }}</td><td class="text-end">{{ number_format($asset->netBookValue(), 2) }}</td><td>{{ $asset->depreciated_to?->format('Y-m-d') ?? '—' }}</td><td>{{ ucfirst($asset->status) }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No fixed assets. Add them under Fixed Assets.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="header"><h2><strong>Previous</strong> runs</h2></div>
                <div class="body">
                    <table class="table table-sm">
                        <thead><tr><th>Period</th><th class="text-end">Assets</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        @forelse($runs as $run)
                            <tr><td>{{ $run->period?->name }}</td><td class="text-end">{{ $run->lines_count }}</td><td class="text-end">{{ number_format((float) $run->total, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">None yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

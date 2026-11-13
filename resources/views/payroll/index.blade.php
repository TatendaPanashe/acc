@extends('layouts.main')

@section('title', 'Payroll')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Payroll', 'section' => 'Operations'])
    @include('payroll._tabs')
    @include('accounting.partials.metrics', ['metrics' => $metrics])

    <div class="row">
        <div class="col-xl-5">
            <div class="card">
                <div class="header"><h2><strong>Setup</strong> checklist</h2></div>
                <div class="body">
                    @foreach($checklist as [$label, $done, $url])
                        <div class="d-flex align-items-center gap-2 border-bottom py-2">
                            <span class="badge {{ $done ? 'bg-success' : 'bg-light text-dark' }}" aria-hidden="true">{{ $done ? '✓' : '·' }}</span>
                            <span class="flex-grow-1">{{ $label }}<span class="visually-hidden">{{ $done ? ' (done)' : ' (to do)' }}</span></span>
                            @unless($done)<a href="{{ $url }}" class="small">Set up →</a>@endunless
                        </div>
                    @endforeach
                    <p class="small text-muted mt-3 mb-0">PAYE tables, NSSA rates and levies are entered by you for each tax year and currency, exactly as ZIMRA and NSSA publish them. acc263 does not ship tax rates.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-center"><h2><strong>Recent</strong> payrolls</h2>
                    @canroute('accounting.payroll.runs.store', 'POST')<a href="{{ route('accounting.payroll.runs.index') }}" class="btn btn-sm btn-primary">New payroll</a>@endcanroute</div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Payroll</th><th>Period</th><th class="text-end">Employees</th><th class="text-end">Cost (base)</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($runs as $run)
                            <tr><td><a href="{{ route('accounting.payroll.runs.show', $run) }}">{{ $run->run_number }}</a></td><td>{{ $run->period_start->format('d M') }} – {{ $run->period_end->format('d M Y') }}</td><td class="text-end">{{ $run->employee_count }}</td><td class="text-end">{{ number_format((float) $run->total_cost_base, 2) }}</td><td>@include('payroll.runs._status', ['run' => $run])</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No payrolls yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

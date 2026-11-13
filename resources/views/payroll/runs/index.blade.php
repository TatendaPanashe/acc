@extends('layouts.main')

@section('title', 'Payroll runs')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Payroll runs', 'section' => 'Payroll'])
    @include('payroll._tabs')

    @canroute('accounting.payroll.runs.store', 'POST')
        <div class="card">
            <div class="header"><h2><strong>New</strong> payroll</h2></div>
            <div class="body">
                <form method="POST" action="{{ route('accounting.payroll.runs.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-2"><label class="form-label">Period from</label><input type="date" name="period_start" class="form-control" required value="{{ old('period_start', $suggested['start']->toDateString()) }}"></div>
                    <div class="col-md-2"><label class="form-label">to</label><input type="date" name="period_end" class="form-control" required value="{{ old('period_end', $suggested['end']->toDateString()) }}"></div>
                    <div class="col-md-2"><label class="form-label">Pay date</label><input type="date" name="pay_date" class="form-control" required value="{{ old('pay_date', $suggested['pay']->toDateString()) }}"></div>
                    <div class="col-md-4"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="1000"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Create payroll</button></div>
                </form>
            </div>
        </div>
    @endcanroute

    <div class="card">
        <div class="body table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Payroll</th><th>Period</th><th>Pay date</th><th class="text-end">Employees</th><th class="text-end">Cost (base)</th><th>Prepared by</th><th>Approved by</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td><a href="{{ route('accounting.payroll.runs.show', $run) }}">{{ $run->run_number }}</a></td>
                        <td>{{ $run->period_start->format('d M') }} – {{ $run->period_end->format('d M Y') }}</td><td>{{ $run->pay_date->format('d M Y') }}</td>
                        <td class="text-end">{{ $run->employee_count }}</td><td class="text-end">{{ number_format((float) $run->total_cost_base, 2) }}</td>
                        <td>{{ $run->creator?->name }}</td><td>{{ $run->approver?->name ?? '—' }}</td><td>@include('payroll.runs._status', ['run' => $run])</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No payrolls yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $runs->links() }}
        </div>
    </div>
@endsection

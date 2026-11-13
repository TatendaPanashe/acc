@extends('staff.layout')

@section('title', 'Home')

@section('content')
    <h1>Hello, {{ $employee->first_name }}</h1>
    <p class="muted">{{ $company?->name }}{{ $employee->job_title ? ' · '.$employee->job_title : '' }}</p>

    <div class="grid" style="margin-bottom:16px">
        <div class="card" style="margin:0">
            <div class="muted small">Latest payslip</div>
            @if($latest)
                <div class="stat">{{ $latest->payslips->map(fn ($p) => $p->currency.' '.number_format((float) $p->net, 2))->join(' + ') }}</div>
                <div class="small muted">Net pay for {{ $latest->period_end->format('F Y') }}, paid {{ $latest->pay_date->format('d M Y') }}</div>
                <a class="btn btn-link" style="padding-left:0" href="{{ route('staff.payslips.pdf', $latest->id) }}" target="_blank">Open payslip (PDF)</a>
            @else
                <div class="stat">—</div><div class="small muted">No payslips yet.</div>
            @endif
        </div>
        <div class="card" style="margin:0">
            <div class="muted small">Requests awaiting approval</div>
            <div class="stat">{{ $pendingLeave + $pendingOvertime }}</div>
            <div class="small muted">{{ $pendingLeave }} leave · {{ $pendingOvertime }} overtime</div>
            <a class="btn btn-link" style="padding-left:0" href="{{ route('staff.leave') }}">Request leave</a> <a class="btn btn-link" href="{{ route('staff.overtime') }}">Claim overtime</a>
        </div>
        <div class="card" style="margin:0">
            <div class="muted small">Upcoming approved leave</div>
            @forelse($upcoming as $leave)
                <div style="margin-top:6px"><strong>{{ $leave->type?->name }}</strong><br><span class="small muted">{{ $leave->starts_on->format('d M') }} – {{ $leave->ends_on->format('d M Y') }} · {{ rtrim(rtrim(number_format((float) $leave->days, 2), '0'), '.') }} days</span></div>
            @empty
                <div class="stat">—</div><div class="small muted">None booked.</div>
            @endforelse
        </div>
    </div>

    <h2>Leave balances {{ now()->year }}</h2>
    @include('staff._balances', ['balances' => $balances])
@endsection

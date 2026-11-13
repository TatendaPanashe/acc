@extends('staff.layout')

@section('title', 'Payslips')

@section('content')
    <h1>Payslips</h1>
    <p class="muted">Payslips appear here once your payroll has been approved.</p>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Month</th><th>Paid</th><th class="num">Gross</th><th class="num">Tax &amp; NSSA</th><th class="num">Net pay</th><th></th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td>{{ $run->period_end->format('F Y') }}</td>
                        <td>{{ $run->pay_date->format('d M Y') }}</td>
                        <td class="num">{!! $run->payslips->map(fn ($p) => e($p->currency.' '.number_format((float) $p->gross, 2)))->join('<br>') !!}</td>
                        <td class="num">{!! $run->payslips->map(fn ($p) => e(number_format((float) $p->paye + (float) $p->aids_levy + (float) $p->nssa_employee, 2)))->join('<br>') !!}</td>
                        <td class="num"><strong>{!! $run->payslips->map(fn ($p) => e($p->currency.' '.number_format((float) $p->net, 2)))->join('<br>') !!}</strong></td>
                        <td class="num"><a href="{{ route('staff.payslips.pdf', $run->id) }}" target="_blank">PDF</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No payslips yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

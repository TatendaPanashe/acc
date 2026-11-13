@extends('layouts.main')

@section('title', $run->run_number)

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Payroll '.$run->run_number, 'section' => 'Payroll'])
    @include('payroll._tabs')

    @php $draft = $run->status === 'draft'; @endphp
    <div class="card">
        <div class="body d-flex justify-content-between flex-wrap gap-3 align-items-center">
            <div>
                <h5 class="mb-1">{{ $run->period_start->format('d M') }} – {{ $run->period_end->format('d M Y') }} · pay date {{ $run->pay_date->format('d M Y') }} @include('payroll.runs._status', ['run' => $run])</h5>
                <small class="text-muted">Prepared by {{ $run->creator?->name ?? '—' }}{{ $run->calculated_at ? ', calculated '.$run->calculated_at->format('d M Y H:i') : '' }}{{ $run->approver ? ' · approved by '.$run->approver->name.' '.$run->approved_at->format('d M Y H:i') : '' }}{{ $run->paid_at ? ' · paid '.$run->paid_at->format('d M Y') : '' }}</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($draft)
                    @canroute('accounting.payroll.runs.calculate', 'POST')<form method="POST" action="{{ route('accounting.payroll.runs.calculate', $run) }}">@csrf<button class="btn btn-primary" @disabled($missing)>{{ $run->calculated_at ? 'Recalculate' : 'Calculate payroll' }}</button></form>@endcanroute
                    @if($run->calculated_at)
                        @canroute('accounting.payroll.runs.approve', 'POST')<form method="POST" action="{{ route('accounting.payroll.runs.approve', $run) }}" onsubmit="return confirm('Approve payroll {{ $run->run_number }} and post it to the ledger?')">@csrf<button class="btn btn-success">Approve &amp; post</button></form>@endcanroute
                    @endif
                    @canroute('accounting.payroll.runs.cancel', 'POST')<form method="POST" action="{{ route('accounting.payroll.runs.cancel', $run) }}" onsubmit="return confirm('Cancel this payroll?')">@csrf<button class="btn btn-outline-danger">Cancel</button></form>@endcanroute
                @elseif($run->status === 'approved')
                    @canroute('accounting.payroll.runs.pay', 'POST')
                        <form method="POST" action="{{ route('accounting.payroll.runs.pay', $run) }}" class="d-flex gap-2" onsubmit="return confirm('Record net pay as paid?')">@csrf
                            <input type="date" name="paid_on" class="form-control form-control-sm" value="{{ $run->pay_date->toDateString() }}" aria-label="Paid on"><button class="btn btn-success btn-sm text-nowrap">Mark net pay paid</button></form>
                    @endcanroute
                @endif
            </div>
        </div>
    </div>

    @if($missing)
        <div class="alert alert-warning">Statutory setup is missing: {{ implode(' ', $missing) }} @canroute('accounting.payroll.settings.index')<a href="{{ route('accounting.payroll.settings.index', ['year' => $run->taxYear()]) }}" class="alert-link">Open statutory settings</a>@endcanroute</div>
    @endif
    @if($draft && !$run->calculated_at && $run->payslips()->exists())<div class="alert alert-info">Inputs changed since the last calculation: recalculate before approval.</div>@endif

    @foreach($run->totals ?? [] as $currency => $t)
        @include('accounting.partials.metrics', ['metrics' => [
            ['Gross pay ('.$currency.')', number_format($t['gross'], 2), 'dollar-sign', 'col-blue', $t['employees'].' employees'],
            ['PAYE + AIDS levy', number_format($t['paye'] + $t['aids_levy'], 2), 'percent', 'col-orange', 'PAYE '.number_format($t['paye'], 2).' · levy '.number_format($t['aids_levy'], 2)],
            ['NSSA', number_format($t['nssa_employee'] + $t['nssa_employer'], 2), 'shield', 'col-purple', 'employee '.number_format($t['nssa_employee'], 2).' · employer '.number_format($t['nssa_employer'], 2)],
            ['Net pay', number_format($t['net'], 2), 'credit-card', 'col-green', 'deductions '.number_format($t['other_deductions'], 2)],
        ]])
    @endforeach

    <div class="row">
        <div class="col-xl-{{ $draft ? '8' : '12' }}">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-center flex-wrap gap-2"><h2><strong>Payroll</strong> register</h2>
                    @if($payslips->isNotEmpty())
                        <div class="d-flex gap-1 flex-wrap">
                            @foreach(['register' => 'Register', 'paye' => 'PAYE schedule', 'nssa' => 'NSSA schedule', 'bank' => 'Payment list'] as $key => $label)<a class="btn btn-sm btn-outline-secondary" href="{{ route('accounting.payroll.runs.export', [$run, $key]) }}">{{ $label }} CSV</a>@endforeach
                        </div>
                    @endif
                </div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Employee</th><th>Cur.</th><th class="text-end">Gross</th><th class="text-end">Taxable</th><th class="text-end">PAYE</th><th class="text-end">AIDS levy</th><th class="text-end">NSSA</th><th class="text-end">Other ded.</th><th class="text-end">Net</th><th></th></tr></thead>
                        <tbody>
                        @forelse($payslips as $employeeId => $slips)
                            @foreach($slips as $i => $slip)
                                <tr class="{{ (float) $slip->net < 0 ? 'table-danger' : '' }}">
                                    @if($i === 0)<td rowspan="{{ $slips->count() }}"><a href="{{ route('accounting.payroll.employees.show', $slip->employee) }}">{{ $slip->employee->name }}</a><small class="d-block text-muted">{{ $slip->employee->employee_number }}</small></td>@endif
                                    <td>{{ $slip->currency }}</td>
                                    <td class="text-end">{{ number_format((float) $slip->gross, 2) }}</td><td class="text-end">{{ number_format((float) $slip->taxable, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $slip->paye, 2) }}</td><td class="text-end">{{ number_format((float) $slip->aids_levy, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $slip->nssa_employee, 2) }}</td><td class="text-end">{{ number_format((float) $slip->other_deductions, 2) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format((float) $slip->net, 2) }}@foreach($slip->breakdown['warnings'] ?? [] as $w)<small class="d-block text-danger">{{ $w }}</small>@endforeach</td>
                                    @if($i === 0)<td rowspan="{{ $slips->count() }}" class="text-end"><a href="{{ route('accounting.payroll.runs.payslip', [$run, $slip->employee]) }}" target="_blank">Payslip</a></td>@endif
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="10" class="text-muted">{{ $draft ? $employees->count().' employees will be paid. Enter variable pay, then calculate.' : 'No payslips.' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if($draft)
            <div class="col-xl-4">
                <div class="card">
                    <div class="header"><h2><strong>Variable</strong> pay this month</h2><small class="text-muted">overtime hours, bonuses, one-off deductions</small></div>
                    <div class="body">
                        @canroute('accounting.payroll.runs.inputs.store', 'POST')
                            @if($payItems->isEmpty())
                                <p class="small text-muted">Create pay items first.</p>
                            @else
                                <form method="POST" action="{{ route('accounting.payroll.runs.inputs.store', $run) }}" class="row g-2 mb-3">
                                    @csrf
                                    <div class="col-12"><select name="employee_id" class="form-control form-control-sm" aria-label="Employee">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_number }} {{ $e->name }}</option>@endforeach</select></div>
                                    <div class="col-12"><select name="pay_item_id" class="form-control form-control-sm" aria-label="Pay item">@foreach($payItems as $p)<option value="{{ $p->id }}">{{ ucfirst($p->type) }}: {{ $p->name }} ({{ \App\Modules\Payroll\Models\PayItem::CALCULATIONS[$p->calculation] }})</option>@endforeach</select></div>
                                    <div class="col-5"><input type="number" step="0.01" min="0" name="quantity" class="form-control form-control-sm" placeholder="Hours"></div>
                                    <div class="col-7"><input type="number" step="0.0001" name="amount" class="form-control form-control-sm" placeholder="Amount or rate"></div>
                                    <div class="col-12"><button class="btn btn-sm btn-primary w-100">Add</button></div>
                                </form>
                            @endif
                        @endcanroute
                        @forelse($inputs as $input)
                            <div class="d-flex justify-content-between align-items-center border-bottom py-1 small">
                                <span>{{ $input->employee?->name }}: {{ $input->payItem?->name }}<br><span class="text-muted">{{ $input->quantity !== null ? (float) $input->quantity.' h' : '' }}{{ $input->amount !== null ? ' '.number_format((float) $input->amount, 2) : '' }}</span></span>
                                @canroute('accounting.payroll.runs.inputs.destroy', 'DELETE')<form method="POST" action="{{ route('accounting.payroll.runs.inputs.destroy', [$run, $input]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger" aria-label="Remove">✕</button></form>@endcanroute
                            </div>
                        @empty
                            <p class="small text-muted mb-0">None: only standing pay applies.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

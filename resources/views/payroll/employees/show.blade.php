@extends('layouts.main')

@section('title', $employee->name)

@section('content')
    @include('accounting.partials.page-header', ['title' => $employee->name, 'section' => 'Payroll'])
    @include('payroll._tabs')

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-start"><div><h2><strong>{{ $employee->employee_number }}</strong></h2><small class="text-muted">{{ $employee->job_title }}{{ $employee->department ? ' · '.$employee->department : '' }}</small></div>
                    @canroute('accounting.payroll.employees.edit')<a href="{{ route('accounting.payroll.employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary">Edit</a>@endcanroute</div>
                <div class="body">
                    <dl class="row mb-0 small">
                        <dt class="col-6">Basic salary</dt><dd class="col-6">{{ $employee->salary_currency }} {{ number_format((float) $employee->basic_salary, 2) }}</dd>
                        <dt class="col-6">Paid in</dt><dd class="col-6">{{ collect($employee->currencySplit())->map(fn ($s, $c) => $c.' '.round($s * 100).'%')->join(' / ') }}</dd>
                        <dt class="col-6">Started</dt><dd class="col-6">{{ $employee->start_date->format('d M Y') }}</dd>
                        @if($employee->end_date)<dt class="col-6">Leaving date</dt><dd class="col-6">{{ $employee->end_date->format('d M Y') }}</dd>@endif
                        <dt class="col-6">TIN</dt><dd class="col-6">{{ $employee->tin ?: '—' }}</dd>
                        <dt class="col-6">NSSA number</dt><dd class="col-6">{{ $employee->nssa_number ?: '—' }}</dd>
                        <dt class="col-6">Paid by</dt><dd class="col-6">{{ \App\Modules\Payroll\Models\Employee::PAYMENT_METHODS[$employee->payment_method] ?? '' }}<br><small class="text-muted">{{ $employee->payment_method === 'mobile_money' ? $employee->mobile_wallet : trim($employee->bank_name.' '.$employee->account_number) }}</small></dd>
                        <dt class="col-6">Tax credits</dt><dd class="col-6">{{ collect($employee->tax_credits ?? [])->map(fn ($c) => $credits[$c] ?? $c)->join(', ') ?: 'None' }}</dd>
                    </dl>
                </div>
            </div>
            <div class="card">
                <div class="header"><h2><strong>Employee</strong> portal</h2></div>
                <div class="body small">
                    @if(session('portal_password'))
                        <div class="alert alert-warning">One-time password: <code class="fs-6">{{ session('portal_password') }}</code><br>Shown only now. They sign in at <a href="{{ route('staff.login') }}">{{ route('staff.login') }}</a> with {{ $employee->email }} and must choose a new password.</div>
                    @endif
                    @if($employee->portal_password)
                        <p class="mb-2">Access is set up for <strong>{{ $employee->email }}</strong>.
                            {{ $employee->portal_password_changed_at ? 'Password set by the employee.' : 'Waiting for first sign-in.' }}
                            {{ $employee->portal_last_login_at ? 'Last signed in '.$employee->portal_last_login_at->diffForHumans().'.' : '' }}</p>
                    @else
                        <p class="mb-2">No portal access yet. The employee signs in with their work email ({{ $employee->email ?: 'not set' }}) to see payslips and request leave and overtime.</p>
                    @endif
                    @canroute('accounting.payroll.employees.portal.issue', 'POST')
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('accounting.payroll.employees.portal.issue', $employee) }}">@csrf<button class="btn btn-sm btn-primary">{{ $employee->portal_password ? 'Issue new one-time password' : 'Set up portal access' }}</button></form>
                            @if($employee->portal_password)<form method="POST" action="{{ route('accounting.payroll.employees.portal.revoke', $employee) }}" onsubmit="return confirm('Remove portal access?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove access</button></form>@endif
                        </div>
                    @endcanroute
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="header"><h2><strong>Standing</strong> earnings &amp; deductions</h2><small class="text-muted">included in every payroll while active; leave the amount blank to use the pay item's default</small></div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Item</th><th>Type</th><th>Calculation</th><th class="text-end">Amount / rate</th><th class="text-end">Hours</th><th>From</th><th>To</th><th></th></tr></thead>
                        <tbody>
                        @forelse($employee->items as $item)
                            <tr>
                                <td>{{ $item->payItem?->name }}</td><td>{{ ucfirst($item->payItem?->type) }}</td><td>{{ \App\Modules\Payroll\Models\PayItem::CALCULATIONS[$item->payItem?->calculation] ?? '' }}</td>
                                <td class="text-end">{{ number_format((float) ($item->amount ?? $item->payItem?->default_amount), 2) }}{{ $item->payItem?->calculation === 'percent_of_basic' ? '%' : '' }}</td>
                                <td class="text-end">{{ $item->quantity !== null ? (float) $item->quantity : '' }}</td>
                                <td>{{ $item->starts_on?->format('d M Y') ?? '—' }}</td><td>{{ $item->ends_on?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">@canroute('accounting.payroll.employees.items.destroy', 'DELETE')<form method="POST" action="{{ route('accounting.payroll.employees.items.destroy', [$employee, $item]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger" aria-label="Remove">✕</button></form>@endcanroute</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted">Only the basic salary is paid.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @canroute('accounting.payroll.employees.items.store', 'POST')
                        @if($payItems->isEmpty())
                            <p class="small text-muted mb-0">Create pay items first (Payroll → Pay items).</p>
                        @else
                            <form method="POST" action="{{ route('accounting.payroll.employees.items.store', $employee) }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-4"><label class="form-label">Pay item</label><select name="pay_item_id" class="form-control form-control-sm">@foreach($payItems as $p)<option value="{{ $p->id }}">{{ ucfirst($p->type) }}: {{ $p->name }}</option>@endforeach</select></div>
                                <div class="col-md-2"><label class="form-label">Amount / rate</label><input type="number" step="0.0001" name="amount" class="form-control form-control-sm" placeholder="Default"></div>
                                <div class="col-md-1"><label class="form-label">Hours</label><input type="number" step="0.01" min="0" name="quantity" class="form-control form-control-sm"></div>
                                <div class="col-md-2"><label class="form-label">From</label><input type="date" name="starts_on" class="form-control form-control-sm"></div>
                                <div class="col-md-2"><label class="form-label">To</label><input type="date" name="ends_on" class="form-control form-control-sm"></div>
                                <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Add</button></div>
                            </form>
                        @endif
                    @endcanroute
                </div>
            </div>
            <div class="card">
                <div class="header"><h2><strong>Payslips</strong></h2></div>
                <div class="body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Payroll</th><th>Currency</th><th class="text-end">Gross</th><th class="text-end">PAYE + AIDS levy</th><th class="text-end">Net</th><th></th></tr></thead>
                        <tbody>
                        @forelse($payslips as $slip)
                            <tr><td><a href="{{ route('accounting.payroll.runs.show', $slip->run) }}">{{ $slip->run?->run_number }}</a></td><td>{{ $slip->currency }}</td><td class="text-end">{{ number_format((float) $slip->gross, 2) }}</td><td class="text-end">{{ number_format((float) $slip->paye + (float) $slip->aids_levy, 2) }}</td><td class="text-end">{{ number_format((float) $slip->net, 2) }}</td>
                                <td class="text-end"><a href="{{ route('accounting.payroll.runs.payslip', [$slip->run, $employee]) }}" target="_blank">Payslip</a></td></tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No payslips yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

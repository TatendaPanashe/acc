@extends('layouts.main')

@section('title', 'Leave & overtime')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Leave & overtime', 'section' => 'Payroll'])
    @include('payroll._tabs')

    @php $canDecide = app(\App\Services\Authorization\RoutePermissions::class)->allows(auth()->user(), 'accounting.payroll.requests.leave.decide', 'POST'); @endphp

    @include('accounting.partials.metrics', ['metrics' => [
        ['Leave requests waiting', $leave->count(), 'calendar', 'col-orange'],
        ['Overtime claims waiting', $overtime->count(), 'clock', 'col-blue', number_format((float) $overtime->sum('hours'), 2).' hours'],
        ['On leave today', $onLeaveToday->count(), 'user-x', 'col-purple', $onLeaveToday->pluck('employee.name')->take(3)->join(', ')],
        ['Employee portal', route('staff.login'), 'globe', 'col-green', 'share this address with staff'],
    ]])

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="header"><h2><strong>Leave</strong> requests</h2></div>
                <div class="body">
                    @forelse($leave as $item)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div><a href="{{ route('accounting.payroll.employees.show', $item->employee) }}"><strong>{{ $item->employee->name }}</strong></a> · {{ $item->type?->name }}
                                    <div class="small text-muted">{{ $item->starts_on->format('D d M') }} – {{ $item->ends_on->format('D d M Y') }} · {{ rtrim(rtrim(number_format((float) $item->days, 2), '0'), '.') }} working days · asked {{ $item->created_at->diffForHumans() }}</div>
                                    @if($item->reason)<div class="small">"{{ $item->reason }}"</div>@endif</div>
                            </div>
                            @if($canDecide)
                                <form method="POST" action="{{ route('accounting.payroll.requests.leave.decide', $item) }}" class="d-flex gap-2 mt-2 flex-wrap">
                                    @csrf
                                    <input name="note" class="form-control form-control-sm" style="max-width:260px" placeholder="Note (required to decline)" maxlength="255">
                                    <button name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                                    <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Decline</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No leave requests waiting.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card">
                <div class="header"><h2><strong>Overtime</strong> claims</h2><small class="text-muted">approved hours are added to the payroll for the month worked</small></div>
                <div class="body">
                    @forelse($overtime as $item)
                        <div class="border-bottom pb-3 mb-3">
                            <a href="{{ route('accounting.payroll.employees.show', $item->employee) }}"><strong>{{ $item->employee->name }}</strong></a> · {{ rtrim(rtrim(number_format((float) $item->hours, 2), '0'), '.') }} hours on {{ $item->work_date->format('D d M Y') }}
                            <div class="small">"{{ $item->reason }}"</div>
                            @if($canDecide)
                                <form method="POST" action="{{ route('accounting.payroll.requests.overtime.decide', $item) }}" class="d-flex gap-2 mt-2 flex-wrap">
                                    @csrf
                                    @if($overtimeItems->isEmpty())
                                        <span class="small text-danger">Create an hourly overtime earning under Pay items to approve overtime.</span>
                                    @else
                                        <select name="pay_item_id" class="form-control form-control-sm" style="max-width:200px" aria-label="Pay as">@foreach($overtimeItems as $p)<option value="{{ $p->id }}">{{ $p->name }} @ {{ number_format((float) $p->default_amount, 2) }}/h</option>@endforeach</select>
                                    @endif
                                    <input name="note" class="form-control form-control-sm" style="max-width:200px" placeholder="Note (required to decline)" maxlength="255">
                                    <button name="decision" value="approve" class="btn btn-sm btn-success" @disabled($overtimeItems->isEmpty())>Approve</button>
                                    <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Decline</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No overtime claims waiting.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="card">
                <div class="header"><h2><strong>Recent</strong> decisions</h2></div>
                <div class="body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Employee</th><th>Request</th><th>Decision</th><th>By</th></tr></thead>
                        <tbody>
                        @foreach($decided->concat($decidedOvertime)->sortByDesc('decided_at')->take(20) as $item)
                            <tr>
                                <td>{{ $item->employee?->name }}</td>
                                <td>@if($item instanceof \App\Modules\Payroll\Models\LeaveRequest){{ $item->type?->name }} {{ $item->starts_on->format('d M') }}–{{ $item->ends_on->format('d M') }} ({{ (float) $item->days }} d)@else Overtime {{ (float) $item->hours }} h on {{ $item->work_date->format('d M') }}{{ $item->run ? ' · paid '.$item->run->run_number : '' }}@endif</td>
                                <td><span class="badge {{ $item->status === 'approved' ? 'bg-success' : 'bg-danger' }}">{{ $item->status === 'approved' ? 'Approved' : 'Declined' }}</span>@if($item->decision_note)<small class="d-block text-muted">{{ $item->decision_note }}</small>@endif</td>
                                <td><small>{{ $item->decider?->name }} {{ $item->decided_at?->format('d M') }}</small></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="header"><h2><strong>Leave</strong> types</h2><small class="text-muted">set the yearly entitlement to track balances; blank = no balance kept</small></div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Name</th><th>Days / year</th><th>Paid</th><th>Active</th><th></th></tr></thead>
                        <tbody>
                        @foreach($types as $type)
                            <tr>
                                <td><input type="hidden" name="code" value="{{ $type->code }}" form="type-{{ $type->id }}"><input name="name" value="{{ $type->name }}" class="form-control form-control-sm" form="type-{{ $type->id }}" required></td>
                                <td><input type="number" step="0.5" min="0" max="366" name="days_per_year" value="{{ $type->days_per_year !== null ? (float) $type->days_per_year : '' }}" class="form-control form-control-sm" form="type-{{ $type->id }}" style="width:90px"></td>
                                <td><input type="checkbox" name="paid" value="1" @checked($type->paid) form="type-{{ $type->id }}" aria-label="Paid"></td>
                                <td><input type="checkbox" name="status" value="1" @checked($type->status) form="type-{{ $type->id }}" aria-label="Active"></td>
                                <td>@canroute('accounting.payroll.leave-types.update', 'PUT')<button class="btn btn-sm btn-primary" form="type-{{ $type->id }}">Save</button>@endcanroute</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @foreach($types as $type)<form method="POST" action="{{ route('accounting.payroll.leave-types.update', $type) }}" id="type-{{ $type->id }}">@csrf @method('PUT')</form>@endforeach
                    @canroute('accounting.payroll.leave-types.store', 'POST')
                        <form method="POST" action="{{ route('accounting.payroll.leave-types.store') }}" class="row g-2 align-items-end mt-2">
                            @csrf
                            <div class="col-3"><input name="code" class="form-control form-control-sm" placeholder="code" required></div>
                            <div class="col-5"><input name="name" class="form-control form-control-sm" placeholder="Name" required></div>
                            <div class="col-2"><input type="number" step="0.5" min="0" name="days_per_year" class="form-control form-control-sm" placeholder="Days"></div>
                            <div class="col-2"><input type="hidden" name="paid" value="1"><input type="hidden" name="status" value="1"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
                        </form>
                    @endcanroute
                    <p class="small text-muted mt-2 mb-0">Entitlements are not pre-set: enter them from your employment contracts and the Labour Act. Public holidays are not deducted automatically.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

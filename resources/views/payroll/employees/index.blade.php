@extends('layouts.main')

@section('title', 'Employees')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Employees', 'section' => 'Payroll'])
    @include('payroll._tabs')

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center flex-wrap gap-2"><h2><strong>Employees</strong></h2>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name or number" aria-label="Search employees">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">@foreach(['active' => 'Active', 'terminated' => 'Left', 'all' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select>
                </form>
                @canroute('accounting.payroll.employees.create')<a href="{{ route('accounting.payroll.employees.create') }}" class="btn btn-sm btn-primary">Add employee</a>@endcanroute
            </div>
        </div>
        <div class="body table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>No.</th><th>Name</th><th>Job</th><th>Department</th><th class="text-end">Basic salary</th><th>Paid in</th><th>Paid by</th></tr></thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr>
                        <td>{{ $employee->employee_number }}</td>
                        <td><a href="{{ route('accounting.payroll.employees.show', $employee) }}">{{ $employee->name }}</a>@if($employee->status === 'terminated')<small class="d-block text-muted">Left {{ $employee->end_date?->format('d M Y') }}</small>@endif</td>
                        <td>{{ $employee->job_title }}</td><td>{{ $employee->department }}</td>
                        <td class="text-end">{{ $employee->salary_currency }} {{ number_format((float) $employee->basic_salary, 2) }}</td>
                        <td>{{ collect($employee->currencySplit())->map(fn ($share, $cur) => $cur.' '.round($share * 100).'%')->join(' / ') }}</td>
                        <td>{{ \App\Modules\Payroll\Models\Employee::PAYMENT_METHODS[$employee->payment_method] ?? $employee->payment_method }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No employees.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $employees->links() }}
        </div>
    </div>
@endsection

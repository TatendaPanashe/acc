@extends('layouts.main')

@section('title', $employee->exists ? 'Edit employee' : 'Add employee')

@section('content')
    @include('accounting.partials.page-header', ['title' => $employee->exists ? 'Edit '.$employee->name : 'Add employee', 'section' => 'Payroll'])
    @include('payroll._tabs')

    @php $v = fn ($field) => old($field, $employee->$field instanceof \Carbon\CarbonInterface ? $employee->$field->toDateString() : $employee->$field); @endphp
    <form method="POST" action="{{ $employee->exists ? route('accounting.payroll.employees.update', $employee) : route('accounting.payroll.employees.store') }}">
        @csrf @if($employee->exists) @method('PUT') @endif
        <div class="card">
            <div class="header"><h2><strong>Personal</strong> details</h2></div>
            <div class="body row g-3">
                <div class="col-md-2"><label class="form-label">Employee no.</label><input name="employee_number" class="form-control" required maxlength="40" value="{{ $v('employee_number') }}"></div>
                <div class="col-md-3"><label class="form-label">First name</label><input name="first_name" class="form-control" required value="{{ $v('first_name') }}"></div>
                <div class="col-md-3"><label class="form-label">Last name</label><input name="last_name" class="form-control" required value="{{ $v('last_name') }}"></div>
                <div class="col-md-2"><label class="form-label">Date of birth</label><input type="date" name="date_of_birth" class="form-control" value="{{ $v('date_of_birth') }}"></div>
                <div class="col-md-2"><label class="form-label">Gender</label><select name="gender" class="form-control"><option value="">—</option>@foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}" @selected($v('gender') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $v('email') }}"></div>
                <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ $v('phone') }}"></div>
                <div class="col-md-2"><label class="form-label">National ID</label><input name="national_id" class="form-control" value="{{ $v('national_id') }}"></div>
                <div class="col-md-2"><label class="form-label">ZIMRA TIN</label><input name="tin" class="form-control" value="{{ $v('tin') }}"></div>
                <div class="col-md-2"><label class="form-label">NSSA number</label><input name="nssa_number" class="form-control" value="{{ $v('nssa_number') }}"></div>
            </div>
        </div>
        <div class="card">
            <div class="header"><h2><strong>Employment</strong> &amp; pay</h2></div>
            <div class="body row g-3">
                <div class="col-md-3"><label class="form-label">Job title</label><input name="job_title" class="form-control" value="{{ $v('job_title') }}"></div>
                <div class="col-md-3"><label class="form-label">Department</label><input name="department" class="form-control" value="{{ $v('department') }}"></div>
                <div class="col-md-2"><label class="form-label">Cost centre</label><input name="cost_centre" class="form-control" value="{{ $v('cost_centre') }}"></div>
                <div class="col-md-2"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control" required value="{{ $v('start_date') }}"></div>
                <div class="col-md-2"><label class="form-label">Leaving date</label><input type="date" name="end_date" class="form-control" value="{{ $v('end_date') }}"></div>
                <div class="col-md-3"><label class="form-label">Basic salary (monthly)</label><input type="number" step="0.01" min="0" name="basic_salary" class="form-control" required value="{{ $v('basic_salary') }}"></div>
                <div class="col-md-2"><label class="form-label">Salary currency</label><select name="salary_currency" class="form-control">@foreach($currencies as $c)<option @selected($v('salary_currency') === $c)>{{ $c }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Part paid in</label><select name="secondary_currency" class="form-control"><option value="">— (all in salary currency)</option>@foreach($currencies as $c)<option @selected($v('secondary_currency') === $c)>{{ $c }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Share paid in it (%)</label><input type="number" step="0.01" min="0" max="100" name="secondary_percent" class="form-control" value="{{ $v('secondary_percent') }}"></div>
                <div class="col-md-3 d-flex align-items-end"><small class="text-muted">Every earning and deduction is split in this proportion, converted at the pay-date rate, and each part is taxed with its own currency's PAYE table.</small></div>
                @if($credits->isNotEmpty())
                    <div class="col-12"><label class="form-label d-block">Tax credits that apply</label>
                        @foreach($credits as $code => $name)<label class="me-3"><input type="checkbox" name="tax_credits[]" value="{{ $code }}" @checked(in_array($code, old('tax_credits', $employee->tax_credits ?? []), true))> {{ $name }}</label>@endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="header"><h2><strong>Payment</strong></h2></div>
            <div class="body row g-3">
                <div class="col-md-3"><label class="form-label">Paid by</label><select name="payment_method" class="form-control">@foreach(\App\Modules\Payroll\Models\Employee::PAYMENT_METHODS as $k => $l)<option value="{{ $k }}" @selected($v('payment_method') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Bank</label><input name="bank_name" class="form-control" value="{{ $v('bank_name') }}"></div>
                <div class="col-md-2"><label class="form-label">Branch</label><input name="bank_branch" class="form-control" value="{{ $v('bank_branch') }}"></div>
                <div class="col-md-2"><label class="form-label">Account number</label><input name="account_number" class="form-control" value="{{ $v('account_number') }}"></div>
                <div class="col-md-2"><label class="form-label">Mobile wallet</label><input name="mobile_wallet" class="form-control" value="{{ $v('mobile_wallet') }}" placeholder="EcoCash number"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ $v('notes') }}</textarea></div>
            </div>
        </div>
        <button class="btn btn-primary">{{ $employee->exists ? 'Save employee' : 'Add employee' }}</button>
        <small class="text-muted ms-2">Changes to pay and bank details are recorded in the audit trail.</small>
    </form>
@endsection

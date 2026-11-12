@csrf
@isset($member) @method('PUT') @endisset
<div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Full name</label><input name="name" class="form-control" value="{{ old('name', $member->name ?? '') }}" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="{{ old('username', $member->username ?? '') }}" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $member->email ?? '') }}" required></div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-control" required>
            @foreach($roles as $key => $label)
                <option value="{{ $key }}" @selected(old('role', $member->role ?? 'accountant') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3"><label class="form-label">Password {{ isset($member) ? '(leave blank to keep current)' : '' }}</label><input type="password" name="password" class="form-control" autocomplete="new-password" @required(!isset($member))></div>
    <div class="col-md-6 mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" @required(!isset($member))></div>
</div>
<div class="alert alert-info">
    <strong>Company Admin</strong>: setup, settings, chart of accounts and team.
    <strong>Finance Manager</strong>: approves and posts journals, supplier invoices, payments, credit notes and orders, and closes periods.
    <strong>Accountant</strong>: day-to-day capture. Can post sales invoices and receipts; everything else goes to a manager for approval.
    <strong>Auditor</strong>: read-only access to all records and reports.
</div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-success">Save</button>
<a href="{{ route('company.team.index') }}" class="btn btn-secondary">Cancel</a>

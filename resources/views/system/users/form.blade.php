@csrf
@if(isset($user)) @method('PUT') @endif
<div class="row">
    <div class="col-md-6 mb-3"><label>Name</label><input name="name" class="form-control" value="{{ old('name', $user->name ?? '') }}" required></div>
    <div class="col-md-6 mb-3"><label>Username</label><input name="username" class="form-control" value="{{ old('username', $user->username ?? '') }}" required></div>
    <div class="col-md-6 mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required></div>
    @if(!isset($user) || !$user->is_superuser)
        <div class="col-md-6 mb-3"><label>Company</label><select name="company_id" class="form-control" required><option value="">Select company</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('company_id', $user->company_id ?? '') == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
        <div class="col-md-6 mb-3"><label>Role</label><select name="role" class="form-control" required><option value="admin" @selected(old('role', $user->role ?? '') === 'admin')>Company Administrator</option><option value="finance_manager" @selected(old('role', $user->role ?? '') === 'finance_manager')>Finance Manager</option><option value="accountant" @selected(old('role', $user->role ?? '') === 'accountant')>Accountant</option><option value="auditor" @selected(old('role', $user->role ?? '') === 'auditor')>Auditor</option></select></div>
    @else
        <div class="col-12 mb-3"><div class="alert alert-info">This is the system-wide superuser account and is not assigned to a company.</div></div>
    @endif
    <div class="col-md-6 mb-3"><label>Password {{ isset($user) ? '(leave blank to keep current password)' : '' }}</label><input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }}></div>
    <div class="col-md-6 mb-3"><label>Confirm Password</label><input type="password" name="password_confirmation" class="form-control" {{ isset($user) ? '' : 'required' }}></div>
</div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-success">Save User</button>
<a href="{{ route('system.users.index') }}" class="btn btn-secondary">Cancel</a>

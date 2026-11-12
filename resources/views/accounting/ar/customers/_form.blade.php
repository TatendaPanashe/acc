@csrf
@isset($customer) @method('PUT') @endisset
<div class="row">
    <div class="col-md-4 mb-3"><label class="form-label">Customer No.</label><input name="customer_number" class="form-control" value="{{ old('customer_number', $customer->customer_number ?? '') }}" required></div>
    <div class="col-md-8 mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $customer->name ?? '') }}" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $customer->email ?? '') }}"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $customer->phone ?? '') }}"></div>
    <div class="col-md-4 mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control" value="{{ old('currency', $customer->currency ?? 'USD') }}"></div>
    <div class="col-md-4 mb-3"><label class="form-label">Credit Limit</label><input type="number" step="0.01" name="credit_limit" class="form-control text-end" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}" required></div>
    <div class="col-md-4 mb-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="status" value="1" class="form-check-input" id="status" @checked(old('status', $customer->status ?? true))><label for="status" class="form-check-label">Active</label></div></div>
    <div class="col-12 mb-3"><label class="form-label">Billing Address</label><textarea name="billing_address" class="form-control">{{ old('billing_address', $customer->billing_address ?? '') }}</textarea></div>
</div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-primary"><i class="fas fa-save"></i> Save Customer</button>

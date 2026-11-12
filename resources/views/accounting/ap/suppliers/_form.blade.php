@csrf
@isset($supplier) @method('PUT') @endisset
<div class="row">
    <div class="col-md-4 mb-3"><label class="form-label">Supplier No.</label><input name="supplier_number" class="form-control" value="{{ old('supplier_number', $supplier->supplier_number ?? '') }}" required></div>
    <div class="col-md-8 mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $supplier->name ?? '') }}" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email ?? '') }}"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $supplier->phone ?? '') }}"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control" value="{{ old('currency', $supplier->currency ?? 'USD') }}"></div>
    <div class="col-md-6 mb-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="status" value="1" class="form-check-input" id="status" @checked(old('status', $supplier->status ?? true))><label for="status" class="form-check-label">Active</label></div></div>
    <div class="col-12 mb-3"><label class="form-label">Remittance Address</label><textarea name="remittance_address" class="form-control">{{ old('remittance_address', $supplier->remittance_address ?? '') }}</textarea></div>
</div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-primary"><i class="fas fa-save"></i> Save Supplier</button>

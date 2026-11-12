@csrf
@if(isset($company)) @method('PUT') @endif
<div class="row">
    <div class="col-md-6 mb-3"><label>Company Name</label><input name="name" class="form-control" value="{{ old('name', $company->name ?? '') }}" required></div>
    <div class="col-md-6 mb-3">
        <label>Company Logo</label>
        <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
        @if(isset($company) && $company->logo_path)
            <img src="{{ asset('storage/'.$company->logo_path) }}" alt="Current company logo" class="mt-2" style="max-height:70px;max-width:220px">
        @endif
        <small class="text-muted d-block">PNG, JPG or WebP, maximum 2 MB.</small>
    </div>
    <div class="col-md-6 mb-3"><label>Registration Number</label><input name="registration_number" class="form-control" value="{{ old('registration_number', $company->registration_number ?? '') }}"></div>
    <div class="col-md-6 mb-3"><label>VAT Number</label><input name="vat_number" class="form-control" value="{{ old('vat_number', $company->vat_number ?? '') }}"></div>
    <div class="col-md-6 mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $company->email ?? '') }}"></div>
    <div class="col-md-6 mb-3"><label>Phone</label><input name="phone" class="form-control" value="{{ old('phone', $company->phone ?? '') }}"></div>
    <div class="col-md-4 mb-3"><label>Country</label><select name="country" class="form-control"><option value="">Not set</option>@foreach(config('platform.countries') as $code => $countryName)<option value="{{ $code }}" @selected(old('country', $company->country ?? '') === $code)>{{ $countryName }}</option>@endforeach</select></div>
    <div class="col-md-8 mb-3"><label>Address</label><textarea name="address" class="form-control">{{ old('address', $company->address ?? '') }}</textarea></div>
    <div class="col-md-4 mb-3"><label>Status</label><select name="status" class="form-control"><option value="1" @selected(old('status', $company->status ?? 1) == 1)>Active</option><option value="0" @selected(old('status', $company->status ?? 1) == 0)>Inactive</option></select></div>
</div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-success">Save Company</button>
<a href="{{ route('system.companies.index') }}" class="btn btn-secondary">Cancel</a>

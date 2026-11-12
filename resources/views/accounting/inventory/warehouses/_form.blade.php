<div class="mb-3"><label class="form-label">Code</label><input name="code" class="form-control" value="{{ old('code', $warehouse->code) }}" required></div>
<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $warehouse->name) }}" required></div>
<div class="mb-3"><label class="form-label">Location</label><input name="location" class="form-control" value="{{ old('location', $warehouse->location) }}"></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

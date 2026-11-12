@csrf
@isset($fiscalYear) @method('PUT') @endisset
<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $fiscalYear->name ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Starts On</label><input type="date" name="starts_on" class="form-control" value="{{ old('starts_on', isset($fiscalYear) ? $fiscalYear->starts_on?->format('Y-m-d') : '') }}" required></div>
<div class="mb-3"><label class="form-label">Ends On</label><input type="date" name="ends_on" class="form-control" value="{{ old('ends_on', isset($fiscalYear) ? $fiscalYear->ends_on?->format('Y-m-d') : '') }}" required></div>
<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-control">
        @foreach(['open', 'locked', 'closed'] as $status)
            <option value="{{ $status }}" @selected(old('status', $fiscalYear->status ?? 'open') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
</div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save</button>

<div class="mb-3"><label class="form-label">SKU</label><input name="sku" class="form-control" value="{{ old('sku', $item->sku) }}" required></div>
<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $item->name) }}" required></div>
<div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Type</label><select name="type" class="form-control"><option value="stock" @selected(old('type', $item->type) === 'stock')>Stock</option><option value="service" @selected(old('type', $item->type) === 'service')>Service</option><option value="non_stock" @selected(old('type', $item->type) === 'non_stock')>Non stock</option></select></div>
    <div class="col-md-6 mb-3"><label class="form-label">Costing</label><select name="costing_method" class="form-control"><option value="weighted_average" @selected(old('costing_method', $item->costing_method) === 'weighted_average')>Weighted average</option><option value="fifo" @selected(old('costing_method', $item->costing_method) === 'fifo')>FIFO</option><option value="standard" @selected(old('costing_method', $item->costing_method) === 'standard')>Standard</option></select></div>
</div>
<div class="row">
    <div class="col-md-4 mb-3"><label class="form-label">Standard Cost</label><input type="number" step="0.01" name="standard_cost" class="form-control text-end" value="{{ old('standard_cost', $item->standard_cost ?? 0) }}" required></div>
    <div class="col-md-4 mb-3"><label class="form-label">Selling Price</label><input type="number" step="0.01" name="selling_price" class="form-control text-end" value="{{ old('selling_price', $item->selling_price ?? 0) }}" required></div>
    <div class="col-md-4 mb-3"><label class="form-label">Reorder</label><input type="number" step="0.0001" name="reorder_level" class="form-control text-end" value="{{ old('reorder_level', $item->reorder_level ?? 0) }}" required></div>
</div>
<div class="form-check mb-2"><input type="checkbox" name="track_serials" value="1" class="form-check-input" id="track_serials" @checked(old('track_serials', $item->track_serials))><label class="form-check-label" for="track_serials">Track serials</label></div>
<div class="form-check mb-3"><input type="checkbox" name="track_lots" value="1" class="form-check-input" id="track_lots" @checked(old('track_lots', $item->track_lots))><label class="form-check-label" for="track_lots">Track lots</label></div>
@if($canAddOpeningStock ?? false)
    <fieldset class="border rounded p-3 mb-3 opening-stock" @if(old('type', $item->type) !== 'stock') hidden @endif>
        <legend class="float-none w-auto px-2 fs-6 mb-0">Quantity on hand</legend>
        @can('approve')
            @if($warehouses->isEmpty())
                <p class="text-muted mb-0">Create a store under Warehouses before entering quantities.</p>
            @else
                <p class="text-muted small mb-2">Enter the quantity currently held in each store. Leave blank for none.</p>
                @foreach($warehouses as $warehouse)
                    <div class="row align-items-center mb-2">
                        <label class="col-7 col-form-label col-form-label-sm" for="opening_{{ $warehouse->id }}">{{ $warehouse->code }} - {{ $warehouse->name }}</label>
                        <div class="col-5"><input type="number" step="0.0001" min="0" id="opening_{{ $warehouse->id }}" name="opening[{{ $warehouse->id }}]" class="form-control form-control-sm text-end" value="{{ old('opening.'.$warehouse->id) }}" placeholder="0"></div>
                    </div>
                @endforeach
                <div class="row mt-2">
                    <div class="col-6"><label class="form-label small mb-1">Unit cost</label><input type="number" step="0.0001" min="0" name="opening_unit_cost" class="form-control form-control-sm text-end" value="{{ old('opening_unit_cost') }}" placeholder="Standard cost"></div>
                    <div class="col-6"><label class="form-label small mb-1">As at</label><input type="date" name="opening_date" class="form-control form-control-sm" value="{{ old('opening_date', now()->toDateString()) }}"></div>
                </div>
                <small class="text-muted d-block mt-2">Posts Dr Inventory / Cr Opening Balance Equity at this cost. The date must fall in an open period.</small>
            @endif
        @else
            <p class="text-muted small mb-0">Quantities on hand are entered by a Finance Manager or Company Admin, or arrive through purchase orders.</p>
        @endcan
    </fieldset>
@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@once
@push('scripts')
<script>
document.addEventListener('change', function (event) {
    if (!event.target.classList.contains('item-type')) return;
    const box = event.target.form.querySelector('.opening-stock');
    if (box) box.hidden = event.target.value !== 'stock';
});
</script>
@endpush
@endonce

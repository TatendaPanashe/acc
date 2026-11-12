@csrf
@php
    $orderLines = old('lines', $order->lines->map(fn($line) => ['item_id' => $line->item_id, 'warehouse_id' => $line->warehouse_id, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_cost' => $line->unit_cost, 'tax_rate' => $line->tax_rate])->toArray());
    if (empty($orderLines)) { $orderLines = [['item_id' => '', 'warehouse_id' => '', 'description' => '', 'quantity' => 1, 'unit_cost' => 0, 'tax_rate' => 15]]; }
@endphp
<div class="row">
    <div class="col-md-4 mb-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-control" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $order->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
    <div class="col-md-2 mb-3"><label class="form-label">Order No.</label><input name="order_number" class="form-control" value="{{ old('order_number', $order->order_number ?: 'PO-'.now()->format('Ymd-His')) }}" required></div>
    <div class="col-md-2 mb-3"><label class="form-label">Order Date</label><input type="date" name="order_date" class="form-control" value="{{ old('order_date', optional($order->order_date)->toDateString() ?: now()->toDateString()) }}" required></div>
    <div class="col-md-2 mb-3"><label class="form-label">Expected</label><input type="date" name="expected_date" class="form-control" value="{{ old('expected_date', optional($order->expected_date)->toDateString()) }}"></div>
    <div class="col-md-2 mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control" value="{{ old('currency', $order->currency ?: 'USD') }}" required></div>
</div>
<div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control">{{ old('notes', $order->notes) }}</textarea></div>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Item</th><th>Warehouse</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Cost</th><th class="text-end">Tax %</th><th></th></tr></thead><tbody class="commercial-lines">
@foreach($orderLines as $i => $line)
<tr><td><select name="lines[{{ $i }}][item_id]" class="form-control" required><option value="">Item</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($line['item_id'] ?? '') == $item->id)>{{ $item->sku }} - {{ $item->name }}</option>@endforeach</select></td><td><select name="lines[{{ $i }}][warehouse_id]" class="form-control" required><option value="">Warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(($line['warehouse_id'] ?? '') == $warehouse->id)>{{ $warehouse->code }}</option>@endforeach</select></td><td><input name="lines[{{ $i }}][description]" class="form-control" value="{{ $line['description'] ?? '' }}"></td><td><input type="number" step="0.0001" name="lines[{{ $i }}][quantity]" class="form-control text-end" value="{{ $line['quantity'] ?? 1 }}" required></td><td><input type="number" step="0.01" name="lines[{{ $i }}][unit_cost]" class="form-control text-end" value="{{ $line['unit_cost'] ?? 0 }}" required></td><td><input type="number" step="0.0001" name="lines[{{ $i }}][tax_rate]" class="form-control text-end" value="{{ $line['tax_rate'] ?? 0 }}"></td><td><button type="button" class="btn btn-light btn-sm line-remove"><i class="fas fa-times"></i></button></td></tr>
@endforeach
</tbody></table></div>
<button type="button" class="btn btn-light btn-sm line-add"><i class="fas fa-plus"></i> Add Line</button>
@if($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="mt-3"><button class="btn btn-primary"><i class="fas fa-save"></i> Save Order</button></div>
@push('scripts')
<script>
document.addEventListener('click', function (event) {
    if (event.target.closest('.line-add')) {
        const body = document.querySelector('.commercial-lines');
        const row = body.querySelector('tr').cloneNode(true);
        const index = body.querySelectorAll('tr').length;
        row.querySelectorAll('input, select').forEach((input) => { input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']'); if (!input.name.includes('tax_rate')) input.value = ''; });
        body.appendChild(row);
    }
    if (event.target.closest('.line-remove') && document.querySelectorAll('.commercial-lines tr').length > 1) event.target.closest('tr').remove();
});
</script>
@endpush

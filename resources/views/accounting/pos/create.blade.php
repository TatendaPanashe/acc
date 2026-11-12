@extends('layouts.main')

@section('title', 'POS Terminal')

@section('content')
@include('accounting.partials.page-header', ['title' => 'POS Terminal', 'section' => 'Point of Sale'])

@php
    $typeLabels = ['stock' => 'Stock item', 'service' => 'Service', 'non_stock' => 'Non-stock item'];
    $itemPayload = $items->map(fn ($item) => [
        'id' => $item->id,
        'sku' => $item->sku,
        'name' => $item->name,
        'type' => $item->type,
        'typeLabel' => $typeLabels[$item->type] ?? ucfirst($item->type),
        'price' => (float) $item->selling_price,
        'tax' => $defaultTaxRate,
        'reorderLevel' => (float) $item->reorder_level,
        'tracking' => collect(['serial numbers' => $item->track_serials, 'lots' => $item->track_lots])->filter()->keys()->join(' and '),
        'stock' => (object) ($stockLevels[$item->id] ?? collect())->all(),
    ])->values();
    $warehousePayload = $warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code.' - '.$w->name]);
@endphp

<form method="POST" action="{{ route('accounting.pos.store') }}" id="posForm">
    @csrf
    <div class="row">
        <div class="col-lg-8">
            <div class="card" id="productDetails">
                <div class="header d-flex justify-content-between align-items-center">
                    <h2><strong>Selected</strong> Product</h2>
                    <span class="badge bg-secondary d-none" id="pdType"></span>
                </div>
                <div class="body">
                    <p class="text-muted mb-0" id="pdEmpty">Select an item on a sale line to see its details.</p>
                    <div class="d-none" id="pdContent">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div>
                                <h4 class="mb-0" id="pdName"></h4>
                                <small class="text-muted">SKU <span id="pdSku"></span></small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Selling price</small>
                                <span class="h4" id="pdPrice"></span>
                                <small class="text-muted d-block">VAT <span id="pdTax"></span>%</small>
                            </div>
                        </div>
                        <div class="row g-3" id="pdStockRow">
                            <div class="col-sm-4">
                                <small class="text-muted d-block">In stock at <span id="pdStoreName">selected store</span></small>
                                <strong class="h5" id="pdStoreQty"></strong>
                            </div>
                            <div class="col-sm-4">
                                <small class="text-muted d-block">Left after this sale</small>
                                <strong class="h5" id="pdAfterSale"></strong>
                            </div>
                            <div class="col-sm-4">
                                <small class="text-muted d-block">All stores</small>
                                <strong class="h5" id="pdTotalQty"></strong>
                            </div>
                            <div class="col-12">
                                <div class="small" id="pdOtherStores"></div>
                                <div class="alert alert-danger py-2 mt-2 mb-0 d-none" id="pdShortage"></div>
                                <div class="alert alert-warning py-2 mt-2 mb-0 d-none" id="pdReorder"></div>
                                <div class="small text-muted mt-1 d-none" id="pdTracking"></div>
                            </div>
                        </div>
                        <p class="text-muted mb-0 d-none" id="pdNoStock">Services and non-stock items are not tracked in inventory.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="header d-flex justify-content-between align-items-center">
                    <h2><strong>Sale</strong> Lines</h2>
                    <button type="button" class="btn btn-light btn-sm" id="addLine"><i class="fas fa-plus"></i> Add Line</button>
                </div>
                <div class="body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th style="min-width:220px">Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Discount</th><th class="text-end">Tax %</th><th class="text-end">Total</th><th></th></tr></thead>
                        <tbody id="posLines">
                            <tr>
                                <td>
                                    <select name="lines[0][item_id]" class="form-control item-select" required><option value="">Scan/select item</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach</select>
                                    <small class="stock-hint d-block mt-1"></small>
                                </td>
                                <td><input type="number" step="0.0001" min="0.0001" name="lines[0][quantity]" class="form-control text-end qty" value="1" required></td>
                                <td><input type="number" step="0.01" min="0" name="lines[0][unit_price]" class="form-control text-end price" value="0" required></td>
                                <td><input type="number" step="0.01" min="0" name="lines[0][discount]" class="form-control text-end discount" value="0"></td>
                                <td><input type="number" step="0.0001" min="0" name="lines[0][tax_rate]" class="form-control text-end tax" value="{{ $defaultTaxRate }}"></td>
                                <td class="text-end line-total">0.00</td>
                                <td><button type="button" class="btn btn-light btn-sm remove-line"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="header"><h2><strong>Tender</strong></h2></div>
                <div class="body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Receipt No.</label><input name="receipt_number" class="form-control" value="POS-{{ now()->format('Ymd-His') }}" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Sold At</label><input type="datetime-local" name="sold_at" class="form-control" value="{{ now()->format('Y-m-d\\TH:i') }}" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Store</label><select name="warehouse_id" id="warehouseSelect" class="form-control" required><option value="">Select store</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouses->count() === 1)>{{ $warehouse->code }} - {{ $warehouse->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">Customer</label><select name="customer_id" class="form-control"><option value="">Walk-in customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Currency</label><select name="currency" class="form-control" required>@foreach($currencies->whenEmpty(fn ($c) => $c->push($baseCurrency)) as $code)<option value="{{ $code }}" @selected($code === $baseCurrency)>{{ $code }}</option>@endforeach</select></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Method</label><select name="payment_method" class="form-control"><option value="cash">Cash</option><option value="card">Card</option><option value="mobile_money">Mobile Money</option><option value="eft">EFT</option></select></div>
                    </div>
                    <div class="border-top pt-3 mb-3">
                        <div class="d-flex justify-content-between"><span>Subtotal</span><strong id="subtotalText">0.00</strong></div>
                        <div class="d-flex justify-content-between"><span>Discount</span><strong id="discountText">0.00</strong></div>
                        <div class="d-flex justify-content-between"><span>Tax</span><strong id="taxText">0.00</strong></div>
                        <div class="d-flex justify-content-between h4 mt-2"><span>Total</span><strong id="totalText">0.00</strong></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Amount Tendered</label><input type="number" step="0.01" name="amount_tendered" class="form-control text-end" id="amountTendered" value="0" required></div>
                    <div class="d-flex justify-content-between mb-3"><span>Change Due</span><strong id="changeText">0.00</strong></div>
                    <button class="btn btn-success btn-lg w-100"><i class="fas fa-check"></i> Complete Sale</button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
const posItems = @json($itemPayload);
const warehouses = @json($warehousePayload);
const defaultTaxRate = @json($defaultTaxRate);
const itemMap = new Map(posItems.map((item) => [String(item.id), item]));
let activeRow = null;

const fmt = (n, dp = 2) => Number(n).toLocaleString(undefined, { minimumFractionDigits: dp, maximumFractionDigits: dp });
const qtyFmt = (n) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 4 });
const el = (id) => document.getElementById(id);
const storeId = () => el('warehouseSelect').value;
const stockAt = (item, warehouseId) => Number(item.stock[warehouseId] ?? 0);
const stockTotal = (item) => Object.values(item.stock).reduce((sum, qty) => sum + Number(qty), 0);

// Quantity of an item across every line of this sale.
function qtyOnSale(itemId) {
    let total = 0;
    document.querySelectorAll('#posLines tr').forEach((row) => {
        if (row.querySelector('.item-select').value === String(itemId)) {
            total += parseFloat(row.querySelector('.qty').value || '0');
        }
    });
    return total;
}

function updateStockHints() {
    document.querySelectorAll('#posLines tr').forEach((row) => {
        const hint = row.querySelector('.stock-hint');
        const item = itemMap.get(row.querySelector('.item-select').value);
        hint.className = 'stock-hint d-block mt-1';
        if (!item) { hint.textContent = ''; return; }
        if (item.type !== 'stock') { hint.textContent = item.typeLabel; hint.classList.add('text-muted'); return; }
        if (!storeId()) { hint.textContent = 'Select a store to see stock'; hint.classList.add('text-muted'); return; }
        const available = stockAt(item, storeId());
        const short = qtyOnSale(item.id) > available;
        hint.textContent = qtyFmt(available) + ' in stock' + (short ? ' (not enough)' : '');
        hint.classList.add(short ? 'text-danger' : (available <= item.reorderLevel ? 'text-warning' : 'text-success'));
    });
}

function showDetails(row) {
    activeRow = row;
    document.querySelectorAll('#posLines tr').forEach((r) => r.classList.toggle('table-active', r === row));
    const item = row ? itemMap.get(row.querySelector('.item-select').value) : null;

    el('pdEmpty').classList.toggle('d-none', !!item);
    el('pdContent').classList.toggle('d-none', !item);
    el('pdType').classList.toggle('d-none', !item);
    if (!item) return;

    el('pdName').textContent = item.name;
    el('pdSku').textContent = item.sku;
    el('pdType').textContent = item.typeLabel;
    el('pdPrice').textContent = fmt(item.price);
    el('pdTax').textContent = qtyFmt(item.tax);

    const tracked = item.type === 'stock';
    el('pdStockRow').classList.toggle('d-none', !tracked);
    el('pdNoStock').classList.toggle('d-none', tracked);
    if (!tracked) return;

    const store = storeId();
    const here = store ? stockAt(item, store) : null;
    const onSale = qtyOnSale(item.id);
    el('pdStoreName').textContent = store ? warehouses[store] : 'selected store';
    el('pdStoreQty').textContent = store ? qtyFmt(here) : '—';
    el('pdAfterSale').textContent = store ? qtyFmt(here - onSale) : '—';
    el('pdAfterSale').className = 'h5 ' + (store && here - onSale < 0 ? 'text-danger' : '');
    el('pdTotalQty').textContent = qtyFmt(stockTotal(item));

    const others = Object.entries(item.stock)
        .filter(([id, qty]) => id !== store && Number(qty) !== 0)
        .map(([id, qty]) => (warehouses[id] ?? 'Store ' + id) + ': ' + qtyFmt(qty));
    el('pdOtherStores').textContent = others.length ? 'Other stores: ' + others.join(' · ') : '';

    const short = store && onSale > here;
    el('pdShortage').classList.toggle('d-none', !short);
    el('pdShortage').textContent = short ? 'Only ' + qtyFmt(here) + ' in stock at this store; this sale needs ' + qtyFmt(onSale) + '.' : '';

    const low = store && !short && here - onSale <= item.reorderLevel && item.reorderLevel > 0;
    el('pdReorder').classList.toggle('d-none', !low);
    el('pdReorder').textContent = low ? 'At or below the reorder level of ' + qtyFmt(item.reorderLevel) + '.' : '';

    el('pdTracking').classList.toggle('d-none', !item.tracking);
    el('pdTracking').textContent = item.tracking ? 'Tracked by ' + item.tracking + '.' : '';
}

function recalcPos() {
    let subtotal = 0, discountTotal = 0, taxTotal = 0;
    document.querySelectorAll('#posLines tr').forEach((row) => {
        const qty = parseFloat(row.querySelector('.qty').value || '0');
        const price = parseFloat(row.querySelector('.price').value || '0');
        const discount = parseFloat(row.querySelector('.discount').value || '0');
        const taxRate = parseFloat(row.querySelector('.tax').value || '0');
        const gross = qty * price;
        const lineDiscount = Math.min(discount, gross);
        const net = gross - lineDiscount;
        const tax = net * taxRate / 100;
        row.querySelector('.line-total').textContent = (net + tax).toFixed(2);
        subtotal += gross; discountTotal += lineDiscount; taxTotal += tax;
    });
    const total = subtotal - discountTotal + taxTotal;
    const tendered = parseFloat(el('amountTendered').value || '0');
    el('subtotalText').textContent = subtotal.toFixed(2);
    el('discountText').textContent = discountTotal.toFixed(2);
    el('taxText').textContent = taxTotal.toFixed(2);
    el('totalText').textContent = total.toFixed(2);
    el('changeText').textContent = Math.max(0, tendered - total).toFixed(2);
    updateStockHints();
    showDetails(activeRow && activeRow.isConnected ? activeRow : null);
}

document.addEventListener('change', function (event) {
    if (event.target.classList.contains('item-select')) {
        const row = event.target.closest('tr');
        const item = itemMap.get(event.target.value);
        if (item) {
            row.querySelector('.price').value = item.price.toFixed(2);
            row.querySelector('.tax').value = item.tax;
        }
        activeRow = row;
    }
    recalcPos();
});
document.addEventListener('input', recalcPos);
document.getElementById('posLines').addEventListener('focusin', (event) => {
    const row = event.target.closest('tr');
    if (row && row !== activeRow) showDetails(row);
});
document.getElementById('posLines').addEventListener('click', (event) => {
    const row = event.target.closest('tr');
    if (row && !event.target.closest('.remove-line') && row !== activeRow) showDetails(row);
});
document.getElementById('addLine').addEventListener('click', function () {
    const body = document.getElementById('posLines');
    const row = body.querySelector('tr').cloneNode(true);
    const index = body.querySelectorAll('tr').length;
    row.classList.remove('table-active');
    row.querySelectorAll('input, select').forEach((input) => {
        input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
        input.value = input.classList.contains('qty') ? '1' : (input.classList.contains('tax') ? defaultTaxRate : '0');
        if (input.tagName === 'SELECT') input.value = '';
    });
    row.querySelector('.line-total').textContent = '0.00';
    row.querySelector('.stock-hint').textContent = '';
    body.appendChild(row);
    row.querySelector('.item-select').focus();
});
document.addEventListener('click', function (event) {
    if (event.target.closest('.remove-line') && document.querySelectorAll('#posLines tr').length > 1) {
        const row = event.target.closest('tr');
        if (row === activeRow) activeRow = null;
        row.remove();
        recalcPos();
    }
});
recalcPos();
</script>
@endpush
@endsection

@extends('layouts.main')

@section('title', $bom->exists ? 'Edit BOM' : 'New BOM')

@section('content')
    @include('accounting.partials.page-header', ['title' => $bom->exists ? 'Edit '.$bom->code : 'New bill of materials', 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    @php $lines = old('lines', $bom->exists ? $bom->lines->map(fn ($l) => $l->only(['item_id', 'quantity', 'scrap_percent', 'notes']))->all() : [['quantity' => 1, 'scrap_percent' => 0]]); @endphp
    <form method="POST" action="{{ $bom->exists ? route('accounting.bom-job-costing.boms.update', $bom) : route('accounting.bom-job-costing.boms.store') }}">
        @csrf @if($bom->exists) @method('PUT') @endif
        <div class="card">
            <div class="header"><h2><strong>What</strong> it makes</h2></div>
            <div class="body row g-3">
                <div class="col-md-2"><label class="form-label">BOM code</label><input name="code" class="form-control" required maxlength="40" value="{{ old('code', $bom->code) }}" placeholder="BOM-CHAIR"></div>
                <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="255" value="{{ old('name', $bom->name) }}"></div>
                <div class="col-md-4"><label class="form-label">Finished item (stock item)</label><select name="item_id" class="form-control" required><option value="">Choose…</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old('item_id', $bom->item_id) == $item->id)>{{ $item->sku }} {{ $item->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Version</label><input name="version" class="form-control" required maxlength="20" value="{{ old('version', $bom->version) }}"></div>
                <div class="col-md-2"><label class="form-label">Batch makes (qty)</label><input type="number" step="0.0001" min="0.0001" name="output_quantity" class="form-control" required value="{{ old('output_quantity', (float) $bom->output_quantity) }}"></div>
                <div class="col-md-2"><label class="form-label">Labour hours / batch</label><input type="number" step="0.0001" min="0" name="labour_hours" class="form-control" value="{{ old('labour_hours', (float) $bom->labour_hours) }}"></div>
                <div class="col-md-2"><label class="form-label">Labour rate / hour</label><input type="number" step="0.01" min="0" name="labour_rate" class="form-control" value="{{ old('labour_rate', (float) $bom->labour_rate) }}"></div>
                <div class="col-md-2"><label class="form-label">Overhead / labour hour</label><input type="number" step="0.01" min="0" name="overhead_rate" class="form-control" value="{{ old('overhead_rate', (float) $bom->overhead_rate) }}"></div>
                <div class="col-md-4 d-flex align-items-end"><label class="d-flex gap-2 align-items-center"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bom->is_active))> Active (replaces any other active BOM for this item)</label></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $bom->notes) }}</textarea></div>
            </div>
        </div>
        <div class="card">
            <div class="header"><h2><strong>Components</strong> per batch</h2><small class="text-muted">A component with its own active BOM is a sub-assembly: it is costed through that BOM and must be made on its own work order.</small></div>
            <div class="body table-responsive">
                <table class="table align-middle" id="bom-lines">
                    <thead><tr><th>Component</th><th style="width:14%">Quantity</th><th style="width:12%">Scrap %</th><th>Notes</th><th></th></tr></thead>
                    <tbody>
                    @foreach($lines as $i => $line)
                        <tr>
                            <td><select name="lines[{{ $i }}][item_id]" class="form-control form-control-sm" required><option value="">Choose…</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($line['item_id'] ?? null) == $item->id)>{{ $item->sku }} {{ $item->name }}</option>@endforeach</select></td>
                            <td><input type="number" step="0.0001" min="0.0001" name="lines[{{ $i }}][quantity]" class="form-control form-control-sm" required value="{{ $line['quantity'] ?? 1 }}"></td>
                            <td><input type="number" step="0.01" min="0" max="100" name="lines[{{ $i }}][scrap_percent]" class="form-control form-control-sm" value="{{ $line['scrap_percent'] ?? 0 }}"></td>
                            <td><input name="lines[{{ $i }}][notes]" class="form-control form-control-sm" maxlength="255" value="{{ $line['notes'] ?? '' }}"></td>
                            <td><button type="button" class="btn btn-sm btn-link text-danger remove-line" aria-label="Remove component">✕</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-line">Add component</button>
            </div>
        </div>
        <button class="btn btn-primary">{{ $bom->exists ? 'Save BOM' : 'Create BOM' }}</button>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.querySelector('#bom-lines tbody');
    function renumber() { body.querySelectorAll('tr').forEach(function (row, i) { row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + i + ']'); }); }); }
    document.getElementById('add-line').addEventListener('click', function () {
        var row = body.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input').forEach(function (el) { el.value = el.name.indexOf('quantity') > -1 ? 1 : (el.name.indexOf('scrap') > -1 ? 0 : ''); });
        row.querySelector('select').value = ''; body.appendChild(row); renumber();
    });
    body.addEventListener('click', function (e) { if (e.target.classList.contains('remove-line') && body.querySelectorAll('tr').length > 1) { e.target.closest('tr').remove(); renumber(); } });
})();
</script>
@endpush

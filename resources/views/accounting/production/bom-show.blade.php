@extends('layouts.main')

@section('title', $bom->code)

@section('content')
    @include('accounting.partials.page-header', ['title' => $bom->code.' · '.$bom->name, 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div><h2><strong>Cost roll-up</strong></h2><small class="text-muted">Makes {{ (float) $bom->output_quantity }} × {{ $bom->item?->sku }} {{ $bom->item?->name }} · version {{ $bom->version }} · {{ $bom->is_active ? 'active' : 'inactive' }}</small></div>
                    @canroute('accounting.bom-job-costing.boms.edit')<a class="btn btn-sm btn-outline-primary" href="{{ route('accounting.bom-job-costing.boms.edit', $bom) }}">Edit</a>@endcanroute
                </div>
                <div class="body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Component</th><th class="text-end">Qty</th><th class="text-end">Scrap</th><th class="text-end">Gross qty</th><th class="text-end">Unit cost</th><th class="text-end">Cost</th><th>Costed from</th></tr></thead>
                        <tbody>
                        @foreach($rollup['lines'] as $line)
                            <tr><td>{{ $line['item']?->sku }} {{ $line['item']?->name }}</td><td class="text-end">{{ $line['quantity'] }}</td><td class="text-end">{{ $line['scrap_percent'] }}%</td><td class="text-end">{{ $line['gross'] }}</td><td class="text-end">{{ number_format($line['unit_cost'], 4) }}</td><td class="text-end">{{ number_format($line['cost'], 2) }}</td><td><small class="text-muted">{{ $line['source'] }}</small></td></tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td colspan="5" class="text-end">Materials</td><td class="text-end">{{ number_format($rollup['materials'], 2) }}</td><td></td></tr>
                            <tr><td colspan="5" class="text-end">Labour ({{ (float) $bom->labour_hours }} h × {{ number_format((float) $bom->labour_rate, 2) }})</td><td class="text-end">{{ number_format($rollup['labour'], 2) }}</td><td></td></tr>
                            <tr><td colspan="5" class="text-end">Overhead ({{ (float) $bom->labour_hours }} h × {{ number_format((float) $bom->overhead_rate, 2) }})</td><td class="text-end">{{ number_format($rollup['overhead'], 2) }}</td><td></td></tr>
                            <tr class="fw-bold"><td colspan="5" class="text-end">Batch cost · unit cost</td><td class="text-end">{{ number_format($rollup['total'], 2) }}</td><td>{{ number_format($rollup['unit_cost'], 4) }} each</td></tr>
                        </tfoot>
                    </table>
                    <small class="text-muted">Component costs use current weighted-average (or standard) cost; sub-assemblies roll up their own BOM.</small>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            @canroute('accounting.bom-job-costing.work-orders.store', 'POST')
                <div class="card">
                    <div class="header"><h2><strong>Make</strong> this item</h2></div>
                    <div class="body">
                        <form method="POST" action="{{ route('accounting.bom-job-costing.work-orders.store') }}" class="row g-2">
                            @csrf <input type="hidden" name="bom_id" value="{{ $bom->id }}">
                            <div class="col-6"><label class="form-label">Work order</label><input name="wo_number" class="form-control" required value="{{ $nextNumber }}"></div>
                            <div class="col-6"><label class="form-label">Quantity</label><input type="number" step="0.0001" min="0.0001" name="quantity" class="form-control" required value="{{ $quantity }}"></div>
                            <div class="col-6"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-control" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} {{ $w->name }}</option>@endforeach</select></div>
                            <div class="col-6"><label class="form-label">Due</label><input type="date" name="due_date" class="form-control"></div>
                            <div class="col-12"><button class="btn btn-primary w-100" @disabled(!$bom->is_active)>Create work order</button>@if(!$bom->is_active)<small class="text-muted">Activate the BOM first.</small>@endif</div>
                        </form>
                    </div>
                </div>
            @endcanroute
            <div class="card">
                <div class="header"><h2><strong>Raw materials</strong> needed</h2><small class="text-muted">all levels, all warehouses</small></div>
                <div class="body">
                    <form method="GET" class="d-flex gap-2 mb-2"><input type="number" step="0.0001" min="0.0001" name="quantity" class="form-control form-control-sm" value="{{ $quantity }}" aria-label="Quantity to make"><button class="btn btn-sm btn-outline-primary">Explode</button></form>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Material</th><th class="text-end">Need</th><th class="text-end">On hand</th></tr></thead>
                        <tbody>
                        @foreach($explosion as $row)
                            <tr class="{{ $row['on_hand'] < $row['quantity'] ? 'table-warning' : '' }}"><td>{{ $row['item']?->sku }}</td><td class="text-end">{{ $row['quantity'] }}</td><td class="text-end">{{ $row['on_hand'] }}@if($row['on_hand'] < $row['quantity']) <small class="text-danger">short</small>@endif</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

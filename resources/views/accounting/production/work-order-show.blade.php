@extends('layouts.main')

@section('title', $order->wo_number)

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Work order '.$order->wo_number, 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    @php
        $steps = ['draft' => 'Draft', 'released' => 'Released', 'in_progress' => 'Materials issued', 'completed' => 'Completed'];
        $reached = array_search($order->status, array_keys($steps), true);
    @endphp
    <div class="card">
        <div class="body">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">{{ (float) $order->quantity }} × {{ $order->item?->sku }} {{ $order->item?->name }}</h5>
                    <small class="text-muted">BOM <a href="{{ route('accounting.bom-job-costing.boms.show', $order->bom) }}">{{ $order->bom?->code }}</a> · warehouse {{ $order->warehouse?->code }} · due {{ $order->due_date?->format('d M Y') ?? '—' }} · created by {{ $order->creator?->name ?? '—' }}</small>
                </div>
                <ol class="list-inline mb-0" aria-label="Progress">
                    @foreach($steps as $key => $label)
                        <li class="list-inline-item"><span class="badge {{ $order->status === 'cancelled' ? 'bg-light text-muted' : ($reached !== false && $loop->index <= $reached ? 'bg-success' : 'bg-light text-dark') }}">{{ $loop->iteration }}. {{ $label }}</span></li>
                    @endforeach
                    @if($order->status === 'cancelled')<li class="list-inline-item"><span class="badge bg-danger">Cancelled</span></li>@endif
                </ol>
            </div>
            @canroute('accounting.bom-job-costing.work-orders.release', 'POST')
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    @if($order->status === 'draft')
                        <form method="POST" action="{{ route('accounting.bom-job-costing.work-orders.release', $order) }}">@csrf<button class="btn btn-primary">Release (fix material requirements)</button></form>
                    @endif
                    @if(in_array($order->status, ['draft', 'released']))
                        <form method="POST" action="{{ route('accounting.bom-job-costing.work-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this work order?')">@csrf<button class="btn btn-outline-danger">Cancel</button></form>
                    @endif
                </div>
            @endcanroute
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="header"><h2><strong>Materials</strong></h2></div>
                <div class="body table-responsive">
                    @if($order->materials->isEmpty())
                        <p class="text-muted mb-0">Material requirements are fixed when the work order is released.</p>
                    @else
                        <form method="POST" action="{{ route('accounting.bom-job-costing.work-orders.issue', $order) }}">
                            @csrf
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Material</th><th class="text-end">Required</th><th class="text-end">Issued</th><th class="text-end">In {{ $order->warehouse?->code }}</th><th class="text-end">Issued cost</th>@if(in_array($order->status, ['released', 'in_progress']))<th style="width:16%">Issue now</th>@endif</tr></thead>
                                <tbody>
                                @foreach($order->materials as $material)
                                    <tr class="{{ $available[$material->id] < $material->remaining() ? 'table-warning' : '' }}">
                                        <td>{{ $material->item?->sku }} {{ $material->item?->name }}</td>
                                        <td class="text-end">{{ (float) $material->required_quantity }}</td>
                                        <td class="text-end">{{ (float) $material->issued_quantity }}</td>
                                        <td class="text-end">{{ $available[$material->id] }}@if($available[$material->id] < $material->remaining()) <small class="text-danger">short</small>@endif</td>
                                        <td class="text-end">{{ number_format((float) $material->issued_cost, 2) }}</td>
                                        @if(in_array($order->status, ['released', 'in_progress']))<td><input type="number" step="0.0001" min="0" max="{{ $material->remaining() }}" name="quantities[{{ $material->id }}]" value="{{ $material->remaining() }}" class="form-control form-control-sm" aria-label="Issue quantity"></td>@endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @if(in_array($order->status, ['released', 'in_progress']))
                                @canroute('accounting.bom-job-costing.work-orders.issue', 'POST')<button class="btn btn-primary">Issue materials to production</button> <small class="text-muted">Dr Work in progress / Cr Inventory</small>@endcanroute
                            @endif
                        </form>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="header"><h2><strong>Cost</strong></h2></div>
                <div class="body">
                    <table class="table table-sm mb-2">
                        <tr><td>Materials issued</td><td class="text-end">{{ number_format((float) $order->material_cost, 2) }}</td></tr>
                        <tr><td>Labour ({{ (float) $order->labour_hours }} h)</td><td class="text-end">{{ number_format((float) $order->labour_cost, 2) }}</td></tr>
                        <tr><td>Overhead</td><td class="text-end">{{ number_format((float) $order->overhead_cost, 2) }}</td></tr>
                        <tr class="fw-bold"><td>Total</td><td class="text-end">{{ number_format($order->totalCost(), 2) }}</td></tr>
                        <tr><td>Standard unit cost</td><td class="text-end">{{ number_format((float) $order->standard_unit_cost, 4) }}</td></tr>
                        @if($order->actualUnitCost() !== null)
                            @php $variance = $order->actualUnitCost() - (float) $order->standard_unit_cost; @endphp
                            <tr><td>Actual unit cost</td><td class="text-end">{{ number_format($order->actualUnitCost(), 4) }}</td></tr>
                            <tr><td>Variance per unit</td><td class="text-end {{ $variance > 0.00005 ? 'text-danger' : 'text-success' }}">{{ $variance > 0 ? '▲ +' : ($variance < 0 ? '▼ ' : '') }}{{ number_format($variance, 4) }} {{ $variance > 0.00005 ? '(adverse)' : ($variance < -0.00005 ? '(favourable)' : '') }}</td></tr>
                        @endif
                    </table>
                    @if($order->status === 'in_progress')
                        @canroute('accounting.bom-job-costing.work-orders.complete', 'POST')
                            <form method="POST" action="{{ route('accounting.bom-job-costing.work-orders.complete', $order) }}" class="row g-2" onsubmit="return confirm('Complete the work order and receive the finished goods?')">
                                @csrf
                                <div class="col-6"><label class="form-label">Quantity made</label><input type="number" step="0.0001" min="0.0001" name="completed_quantity" class="form-control" required value="{{ (float) $order->quantity }}"></div>
                                <div class="col-6"><label class="form-label">Labour hours</label><input type="number" step="0.0001" min="0" name="labour_hours" class="form-control" placeholder="From BOM"></div>
                                <div class="col-12"><button class="btn btn-success w-100">Complete &amp; receive finished goods</button><small class="text-muted">Applies labour and overhead to WIP, then Dr Inventory / Cr WIP at actual cost.</small></div>
                            </form>
                        @endcanroute
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

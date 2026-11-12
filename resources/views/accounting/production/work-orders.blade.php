@extends('layouts.main')

@section('title', 'Work orders')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Work orders', 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    @canroute('accounting.bom-job-costing.work-orders.store', 'POST')
        <div class="card">
            <div class="header"><h2><strong>New</strong> work order</h2></div>
            <div class="body">
                @if($boms->isEmpty())
                    <p class="text-muted mb-0">Create an active bill of materials first.</p>
                @else
                    <form method="POST" action="{{ route('accounting.bom-job-costing.work-orders.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-2"><label class="form-label">Number</label><input name="wo_number" class="form-control" required value="{{ old('wo_number', $nextNumber) }}"></div>
                        <div class="col-md-3"><label class="form-label">BOM</label><select name="bom_id" class="form-control" required>@foreach($boms as $bom)<option value="{{ $bom->id }}">{{ $bom->code }} → {{ $bom->item?->sku }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" step="0.0001" min="0.0001" name="quantity" class="form-control" required></div>
                        <div class="col-md-2"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-control" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label">Due</label><input type="date" name="due_date" class="form-control"></div>
                        <div class="col-md-1"><button class="btn btn-primary w-100">Create</button></div>
                    </form>
                @endif
            </div>
        </div>
    @endcanroute

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center"><h2><strong>Work</strong> orders</h2>
            <form method="GET"><select name="status" class="form-control form-control-sm" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(\App\Modules\Accounting\Models\WorkOrder::STATUSES as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select></form></div>
        <div class="body table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Number</th><th>Making</th><th class="text-end">Qty</th><th>Warehouse</th><th>Due</th><th class="text-end">Cost so far</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr><td><a href="{{ route('accounting.bom-job-costing.work-orders.show', $order) }}">{{ $order->wo_number }}</a></td><td>{{ $order->item?->sku }} {{ $order->item?->name }}</td><td class="text-end">{{ (float) $order->quantity }}</td><td>{{ $order->warehouse?->code }}</td><td>{{ $order->due_date?->format('d M Y') ?? '—' }}</td><td class="text-end">{{ number_format($order->totalCost(), 2) }}</td><td>{{ \App\Modules\Accounting\Models\WorkOrder::STATUSES[$order->status] ?? $order->status }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No work orders.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $orders->links() }}
        </div>
    </div>
@endsection

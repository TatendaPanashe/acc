@extends('layouts.main')

@section('title', 'Bills of materials')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Bills of materials', 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center"><h2><strong>Bills</strong> of materials</h2>
            @canroute('accounting.bom-job-costing.boms.create')<a href="{{ route('accounting.bom-job-costing.boms.create') }}" class="btn btn-sm btn-primary">New BOM</a>@endcanroute</div>
        <div class="body table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Code</th><th>Makes</th><th>Version</th><th class="text-end">Batch size</th><th class="text-end">Components</th><th class="text-end">Labour h / batch</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($boms as $bom)
                    <tr>
                        <td><a href="{{ route('accounting.bom-job-costing.boms.show', $bom) }}">{{ $bom->code }}</a><small class="d-block text-muted">{{ $bom->name }}</small></td>
                        <td>{{ $bom->item?->sku }} {{ $bom->item?->name }}</td><td>{{ $bom->version }}</td>
                        <td class="text-end">{{ (float) $bom->output_quantity }}</td><td class="text-end">{{ $bom->lines_count }}</td><td class="text-end">{{ (float) $bom->labour_hours }}</td>
                        <td><span class="badge {{ $bom->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $bom->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No BOMs yet. A BOM lists the stock items (and labour) needed to make a stock item.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $boms->links() }}
        </div>
    </div>
@endsection

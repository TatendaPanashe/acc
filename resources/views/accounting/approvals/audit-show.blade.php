@extends('layouts.main')

@section('title', 'Audit entry')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Audit entry #'.$entry->id, 'section' => 'Audit trail'])
    @include('accounting.approvals._tabs')

    <div class="card">
        <div class="header"><h2><strong>{{ $entry->auditable_type ? str(class_basename($entry->auditable_type))->headline().' #'.$entry->auditable_id : $entry->event }}</strong> history</h2></div>
        <div class="body table-responsive">
            <table class="table table-sm align-top">
                <thead><tr><th>When</th><th>User</th><th>Event</th><th>Field</th><th>Before</th><th>After</th></tr></thead>
                <tbody>
                @foreach($history as $log)
                    @php $fields = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))); @endphp
                    @forelse($fields as $i => $field)
                        <tr class="{{ $log->id === $entry->id ? 'table-warning' : '' }}">
                            @if($i === 0)
                                <td rowspan="{{ count($fields) }}" class="text-nowrap">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                                <td rowspan="{{ count($fields) }}">{{ $log->user?->name ?? 'System' }}<small class="d-block text-muted">{{ $log->ip_address }}</small></td>
                                <td rowspan="{{ count($fields) }}"><code>{{ $log->event }}</code></td>
                            @endif
                            <td>{{ $field }}</td>
                            <td class="text-muted">{{ is_scalar($log->old_values[$field] ?? null) ? $log->old_values[$field] : json_encode($log->old_values[$field] ?? null) }}</td>
                            <td>{{ is_scalar($log->new_values[$field] ?? null) ? $log->new_values[$field] : json_encode($log->new_values[$field] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr class="{{ $log->id === $entry->id ? 'table-warning' : '' }}"><td class="text-nowrap">{{ $log->created_at?->format('d M Y H:i:s') }}</td><td>{{ $log->user?->name ?? 'System' }}</td><td><code>{{ $log->event }}</code></td><td colspan="3" class="text-muted">No field values recorded.</td></tr>
                    @endforelse
                @endforeach
                </tbody>
            </table>
            <p class="small text-muted mb-0">Checksum of the highlighted entry: <code>{{ $entry->checksum ?? 'none (recorded before checksums were introduced)' }}</code></p>
        </div>
    </div>
@endsection

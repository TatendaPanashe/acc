@extends('layouts.main')

@section('title', 'Audit trail')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Audit trail', 'section' => 'Approvals & Audit'])
    @include('accounting.approvals._tabs')

    @if($verification)
        <div class="alert {{ $verification['valid'] ? 'alert-success' : 'alert-danger' }}">
            @if($verification['valid'])
                <strong>Audit trail intact.</strong> {{ number_format($verification['checked']) }} entries checked; every checksum matches the chain.
            @else
                <strong>Audit trail tampering detected.</strong> Entry #{{ $verification['broken_at'] }} does not match its checksum: it, or an entry before it, was changed or removed outside acc263.
            @endif
        </div>
    @endif

    <div class="card">
        <div class="body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label">User</label><select name="user_id" class="form-control"><option value="">Anyone</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Record type</label><select name="subject" class="form-control"><option value="">All</option>@foreach($subjects as $class => $label)<option value="{{ $class }}" @selected(($filters['subject'] ?? null) === $class)>{{ str($label)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Event contains</label><input name="event" class="form-control" value="{{ $filters['event'] ?? '' }}" placeholder="posted, deleted, login"></div>
                <div class="col-md-2"><label class="form-label">Value contains</label><input name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="INV-0042"></div>
                <div class="col-md-1"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}"></div>
                <div class="col-md-1"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}"></div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-primary">Filter</button>
                    <a class="btn btn-outline-secondary" href="{{ route('accounting.approvals-audit.audit.export', request()->query()) }}">CSV</a>
                    <a class="btn btn-outline-success" href="{{ route('accounting.approvals-audit.audit.index', ['verify' => 1]) }}" title="Recompute the checksum chain">Verify</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="body table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th>When</th><th>User</th><th>Event</th><th>Record</th><th>Changes</th><th>IP</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('accounting.approvals-audit.audit.show', $log->id) }}">{{ $log->created_at?->format('d M Y H:i:s') }}</a></td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td><code>{{ $log->event }}</code></td>
                        <td>{{ $log->auditable_type ? str(class_basename($log->auditable_type))->headline().' #'.$log->auditable_id : '—' }}</td>
                        <td><small class="text-muted">
                            @php $keys = array_keys(($log->new_values ?? []) + ($log->old_values ?? [])); @endphp
                            {{ \Illuminate\Support\Str::limit(implode(', ', array_slice($keys, 0, 6)), 80) }}{{ count($keys) > 6 ? ' +'.(count($keys) - 6) : '' }}
                        </small></td>
                        <td><small>{{ $log->ip_address }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No audit entries match.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $logs->links() }}
        </div>
    </div>
@endsection

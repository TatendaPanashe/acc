@extends('layouts.main')

@section('title', 'Approvals & Audit')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Approvals & Audit', 'section' => 'Controls'])
    @include('accounting.approvals._tabs')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @include('accounting.partials.metrics', ['metrics' => [
        [$canApprove ? 'Awaiting approval' : 'My documents awaiting approval', $counts['pending'], 'inbox', 'col-orange'],
        ['Returned for correction', $counts['returned'], 'corner-up-left', 'col-red'],
        ['Approved (30 days)', $counts['approved_30'], 'check-circle', 'col-green'],
        ['Returned (30 days)', $counts['rejected_30'], 'x-circle', 'col-purple'],
    ]])

    <div class="card">
        <div class="header"><h2><strong>{{ $canApprove ? 'Approval' : 'My' }}</strong> inbox</h2></div>
        <div class="body table-responsive">
            @if($items->isEmpty())
                <p class="text-muted mb-0">{{ $canApprove ? 'Nothing is waiting for approval.' : 'None of your documents are waiting for approval.' }}</p>
            @else
                <table class="table table-hover align-middle">
                    <thead><tr><th>Document</th><th>Date</th><th class="text-end">Amount</th><th>Prepared by</th><th>Waiting</th><th>Approver</th><th class="text-end">{{ $canApprove ? 'Decision' : 'Status' }}</th></tr></thead>
                    <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>
                                <small class="text-muted d-block">{{ $item['label'] }}</small>
                                @if($item['url'])<a href="{{ $item['url'] }}">{{ $item['number'] }}</a>@else{{ $item['number'] }}@endif
                                @if($item['rejection'])
                                    <small class="d-block text-danger">Returned by {{ $item['rejection']->resolver?->name }}: {{ $item['rejection']->resolution_note }}</small>
                                @endif
                            </td>
                            <td>{{ $item['date']?->format('d M Y') }}</td>
                            <td class="text-end">{{ $item['amount'] === null ? '—' : trim(($item['currency'] ?? '').' '.number_format($item['amount'], 2)) }}</td>
                            <td>{{ $item['creator'] ?? '—' }}</td>
                            <td>{{ $item['age_days'] === 0 ? 'Today' : $item['age_days'].' '.str('day')->plural($item['age_days']) }}</td>
                            <td><small>{{ $item['needs'] }}</small></td>
                            <td class="text-end">
                                @if(!$canApprove)
                                    <span class="badge {{ $item['rejection'] ? 'bg-danger' : 'bg-warning text-dark' }}">{{ $item['rejection'] ? 'Returned' : 'Pending' }}</span>
                                @elseif($item['blocked'])
                                    <small class="text-muted d-inline-block" style="max-width:240px">{{ $item['blocked'] }}</small>
                                @else
                                    <div class="d-inline-flex gap-1">
                                        <form method="POST" action="{{ $item['approve_route'] }}" onsubmit="return confirm('{{ $item['action'] }} {{ $item['number'] }}?')">
                                            @csrf <button class="btn btn-sm btn-success">{{ $item['action'] }}</button>
                                        </form>
                                        @canroute('accounting.approvals-audit.reject', 'POST')
                                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#reject-{{ $item['type'] }}-{{ $item['document']->id }}">Return</button>
                                        @endcanroute
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @if($canApprove && !$item['blocked'])
                            <tr class="collapse" id="reject-{{ $item['type'] }}-{{ $item['document']->id }}">
                                <td colspan="7">
                                    <form method="POST" action="{{ route('accounting.approvals-audit.reject', [$item['type'], $item['document']->id]) }}" class="d-flex gap-2">
                                        @csrf
                                        <input name="note" class="form-control form-control-sm" maxlength="500" required placeholder="Reason, shown to the preparer (e.g. attach the quote, wrong account)">
                                        <button class="btn btn-sm btn-danger">Return for correction</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($canApprove)
        <div class="card">
            <div class="header"><h2><strong>Recent</strong> decisions</h2></div>
            <div class="body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>When</th><th>Document</th><th class="text-end">Amount</th><th>Prepared by</th><th>Decided by</th><th>Decision</th><th>Note</th></tr></thead>
                    <tbody>
                    @forelse($decisions as $decision)
                        <tr>
                            <td>{{ $decision->resolved_at?->format('d M Y H:i') }}</td>
                            <td>{{ $types[$decision->workflow_key] ?? $decision->workflow_key }} {{ $decision->reference }}</td>
                            <td class="text-end">{{ $decision->amount === null ? '—' : number_format((float) $decision->amount, 2) }}</td>
                            <td>{{ $decision->requester?->name ?? '—' }}</td>
                            <td>{{ $decision->resolver?->name }}</td>
                            <td><span class="badge {{ $decision->status === 'approved' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($decision->status) }}</span></td>
                            <td><small>{{ $decision->resolution_note }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No decisions yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

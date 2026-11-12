@extends('layouts.main')

@section('title', 'Anomaly detection')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Anomaly detection', 'section' => 'AI/ML Automation'])
    @include('accounting.ai._tabs')

    <div class="card">
        <div class="body d-flex justify-content-between flex-wrap gap-2 align-items-end">
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">@foreach(['open' => 'Open', 'confirmed' => 'Confirmed', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select>
                <select name="type" class="form-control form-control-sm" onchange="this.form.submit()"><option value="">All checks</option>@foreach(\App\Modules\Accounting\Models\AiInsight::TYPES as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}{{ isset($counts[$k]) ? ' ('.$counts[$k].' open)' : '' }}</option>@endforeach</select>
            </form>
            @canroute('accounting.ai-automation.insights.run', 'POST')<form method="POST" action="{{ route('accounting.ai-automation.insights.run') }}">@csrf<button class="btn btn-sm btn-primary">Scan transactions now</button></form>@endcanroute
        </div>
    </div>

    <div class="card">
        <div class="body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Severity</th><th>Finding</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($insights as $insight)
                    @include('accounting.ai._insight-row', ['insight' => $insight])
                @empty
                    <tr><td colspan="4" class="text-muted">Nothing here.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $insights->links() }}
        </div>
    </div>

    <div class="card">
        <div class="header"><h2><strong>What</strong> is checked</h2></div>
        <div class="body small">
            <ul class="mb-0">
                <li><strong>Unusual amounts:</strong> expense postings far outside the account's normal range (robust z-score above 3.5 against the median and median absolute deviation of earlier postings).</li>
                <li><strong>Possible duplicate supplier invoices:</strong> the same supplier with the same invoice number, or the same total within 14 days.</li>
                <li><strong>Manual journal controls:</strong> large round amounts, weekend postings, and journals posted by the person who prepared them.</li>
                <li><strong>Benford's law:</strong> with 50 or more supplier invoices, the first-digit distribution is compared with the natural pattern; a strong deviation can point to invented figures.</li>
            </ul>
            <p class="text-muted mb-0 mt-2">Dismissed findings are not raised again. Confirmed findings stay on record for follow-up and appear in the audit trail.</p>
        </div>
    </div>
@endsection

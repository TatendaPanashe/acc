@php $url = \App\Modules\Accounting\Http\Controllers\AiAutomationController::subjectUrl($insight); @endphp
<tr>
    <td><span class="badge {{ ['high' => 'bg-danger', 'medium' => 'bg-warning text-dark', 'low' => 'bg-secondary'][$insight->severity] ?? 'bg-light' }}">{{ ucfirst($insight->severity) }}</span></td>
    <td>
        {{ $insight->title }}
        <small class="d-block text-muted">{{ \App\Modules\Accounting\Models\AiInsight::TYPES[$insight->type] ?? $insight->type }}
            @if($insight->subject_label) · @if($url)<a href="{{ $url }}">{{ $insight->subject_label }}</a>@else{{ $insight->subject_label }}@endif @endif
            @foreach(array_slice($insight->details ?? [], 0, 4) as $k => $v)@if(is_scalar($v) && $v !== '') · {{ str_replace('_', ' ', $k) }}: {{ $v }}@endif @endforeach
        </small>
        @if($insight->review_note)<small class="d-block">Note: {{ $insight->review_note }}</small>@endif
    </td>
    <td class="text-end text-nowrap">{{ $insight->amount !== null ? number_format((float) $insight->amount, 2) : '' }}</td>
    @if($actions ?? true)
        <td class="text-end text-nowrap">
            @if($insight->status === 'open')
                @canroute('accounting.ai-automation.insights.review', 'POST')
                    <form method="POST" action="{{ route('accounting.ai-automation.insights.review', $insight) }}" class="d-inline">@csrf<input type="hidden" name="status" value="confirmed"><button class="btn btn-sm btn-outline-danger" title="Confirm: this needs action">Confirm</button></form>
                    <form method="POST" action="{{ route('accounting.ai-automation.insights.review', $insight) }}" class="d-inline">@csrf<input type="hidden" name="status" value="dismissed"><button class="btn btn-sm btn-outline-secondary" title="Dismiss: this is fine">Dismiss</button></form>
                @endcanroute
            @else
                <small class="text-muted">{{ ucfirst($insight->status) }} by {{ $insight->reviewer?->name }}</small>
            @endif
        </td>
    @endif
</tr>

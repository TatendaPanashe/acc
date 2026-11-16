@php
    $brand = $brand ?? ['name' => config('app.name'), 'initials' => strtoupper(substr(config('app.name'), 0, 1))];
    $clean = fn ($lines) => array_values(array_filter($lines, fn ($l) => trim((string) $l) !== ''));
    $outroLines = $clean($outroLines);
    $buttonColor = match ($level ?? 'info') { 'success', 'error' => $level, default => 'primary' };
@endphp
<x-mail::layout>
<x-slot:header>
<x-mail::brand :brand="$brand" />
</x-slot:header>

@if(!empty($preheader))
<div class="preheader">{{ $preheader }}</div>
@endif

@if(!empty($eyebrow))
<p class="eyebrow">{{ $eyebrow }}</p>
@endif

@if (! empty($greeting))
# {{ $greeting }}
@endif

@foreach(($blocks ?? []) as $block)
@if($block['type'] === 'line')
@continue(trim((string) $block['text']) === '')
{{ $block['text'] }}

@elseif($block['type'] === 'highlight')
<x-mail::highlight :label="$block['label']" :value="$block['value']" :note="$block['note']" :tone="$block['tone']" />

@elseif($block['type'] === 'summary')
<x-mail::summary :rows="$block['rows']" :title="$block['title']" />

@elseif($block['type'] === 'items')
<x-mail::items :rows="$block['rows']" :total="$block['total']" :total-label="$block['totalLabel']" :head="$block['head']" />

@elseif($block['type'] === 'note')
<x-mail::note :title="$block['title']" :text="$block['text']" :tone="$block['tone']" />

@endif
@endforeach
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$buttonColor">
{{ $actionText }}
</x-mail::button>

@endisset
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
@if (! empty($salutation))
{{ $salutation }}
@else
Regards,<br>
The {{ config('app.name') }} team
@endif

@isset($actionText)
<x-slot:subcopy>
<x-mail::subcopy>
If the “{{ $actionText }}” button does not work, copy this link into your browser: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
@if(!empty($brand['contact']))
**{{ $brand['name'] }}** · {{ $brand['contact'] }}
@endif

@if(!empty($footnote))
{{ $footnote }}
@endif

@if(!empty($brand['company']))
Sent on behalf of {{ $brand['name'] }} by [{{ config('app.name') }}]({{ config('app.url') }}).
@else
© {{ date('Y') }} [{{ config('app.name') }}]({{ config('app.url') }}) · Accounting, payroll and invoicing for growing businesses.
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

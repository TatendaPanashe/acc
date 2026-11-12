@props(['rows', 'name' => 'Value', 'width' => 520, 'empty' => 'No data for this period.'])
{{-- Ranked horizontal bars, one series: magnitude by length, value at the bar end, tooltip per bar. --}}
<x-charts.styles/>
@php
    $rows = collect($rows)->values();
    $max = max(1.0, (float) $rows->max('value'));
    $rowH = 30; $labelW = 170; $valueW = 90; $W = $width; $H = max($rowH, $rows->count() * $rowH);
    $barMax = $W - $labelW - $valueW;
@endphp
<div class="ac-chart">
    @if($rows->isEmpty())
        <p class="text-muted mb-0">{{ $empty }}</p>
    @else
        <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $name }}, ranked">
            @foreach($rows as $i => $row)
                @php
                    $y = $i * $rowH; $w = max(2, ((float) $row['value'] / $max) * $barMax); $r = min(4, $w / 2);
                    $tip = $row['label'].': '.number_format((float) $row['value'], 2).(isset($row['sub']) ? "\n".$row['sub'] : '');
                @endphp
                <text x="{{ $labelW - 10 }}" y="{{ $y + 19 }}" text-anchor="end" class="bar-label">{{ \Illuminate\Support\Str::limit($row['label'], 26) }}</text>
                <path d="M{{ $labelW }},{{ $y + 7 }} L{{ $labelW + $w - $r }},{{ $y + 7 }} Q{{ $labelW + $w }},{{ $y + 7 }} {{ $labelW + $w }},{{ $y + 7 + $r }} L{{ $labelW + $w }},{{ $y + 23 - $r }} Q{{ $labelW + $w }},{{ $y + 23 }} {{ $labelW + $w - $r }},{{ $y + 23 }} L{{ $labelW }},{{ $y + 23 }} Z" fill="{{ $row['colour'] ?? 'var(--series-1)' }}"/>
                <text x="{{ $labelW + $w + 8 }}" y="{{ $y + 19 }}" class="value-label">{{ number_format((float) $row['value'], 0) }}</text>
                <rect class="hit" x="0" y="{{ $y }}" width="{{ $W }}" height="{{ $rowH }}" fill="transparent" tabindex="0" data-tip="{{ $tip }}" aria-label="{{ str_replace("\n", ', ', $tip) }}"/>
            @endforeach
        </svg>
        <details>
            <summary>View as table</summary>
            <table class="table table-sm mt-2 mb-0"><tbody>@foreach($rows as $row)<tr><td>{{ $row['label'] }}</td><td class="text-end">{{ number_format((float) $row['value'], 2) }}</td></tr>@endforeach</tbody></table>
        </details>
    @endif
</div>

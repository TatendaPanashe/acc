@props(['labels', 'series', 'money' => true, 'height' => 260, 'width' => 760, 'name' => 'Trend'])
{{-- Multi-series line chart (max 3 series, fixed slot order). A value may be null (gap): e.g. actuals then forecast. Legend always shown for 2+ series; each line is
     direct-labelled at its last point; every value is in the hover tooltip and the table view. --}}
<x-charts.styles/>
@php
    $labels = array_values($labels);
    $series = array_slice(array_values($series), 0, 3);
    $W = $width; $H = $height; $left = 58; $right = 86; $top = 14; $bottom = 28;
    $plotW = $W - $left - $right; $plotH = $H - $top - $bottom; $base = $top + $plotH;
    $all = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null);
    $max = max(1.0, (float) $all->max()); $min = min(0.0, (float) $all->min());
    $rawStep = ($max - $min) / 4; $mag = 10 ** floor(log10(max($rawStep, 1)));
    $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s >= $rawStep) ?? $rawStep;
    $axisMax = ceil($max / $step) * $step; $axisMin = floor($min / $step) * $step; $span = max($axisMax - $axisMin, 1);
    $n = count($labels); $dx = $n > 1 ? $plotW / ($n - 1) : 0;
    $xAt = fn ($i) => $left + $dx * $i;
    $yAt = fn ($v) => $base - (($v - $axisMin) / $span) * $plotH;
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 0);
    $lastIndex = fn ($values) => collect($values)->filter(fn ($v) => $v !== null)->keys()->last() ?? 0;
    $compact = function ($v) { $a = abs($v); return ($v < 0 ? '-' : '').($a >= 1e6 ? round($a / 1e6, 1).'M' : ($a >= 1e3 ? round($a / 1e3, 1).'k' : number_format($a))); };
    $slots = ['var(--series-1)', 'var(--series-2)', 'var(--series-3)'];
    // Direct labels at the right end; nudge apart when two lines end close together.
    $ends = collect($series)->map(fn ($s, $k) => ['k' => $k, 'y' => $yAt($s['values'][$lastIndex($s['values'])] ?? 0)])->sortBy('y')->values();
    for ($i = 1; $i < $ends->count(); $i++) { if ($ends[$i]['y'] - $ends[$i - 1]['y'] < 14) { $e = $ends[$i]; $e['y'] = $ends[$i - 1]['y'] + 14; $ends[$i] = $e; } }
    $endY = $ends->pluck('y', 'k');
@endphp
<div class="ac-chart">
    @if(count($series) > 1)
        <div class="legend">@foreach($series as $k => $s)<span><i style="background: {{ !empty($s['dashed']) ? 'repeating-linear-gradient(90deg, '.$slots[$k].' 0 4px, transparent 4px 6px)' : $slots[$k] }}"></i>{{ $s['name'] }}</span>@endforeach</div>
    @endif
    <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $name }}: {{ collect($series)->pluck('name')->join(', ') }} from {{ $labels[0] ?? '' }} to {{ end($labels) ?: '' }}">
        @for($v = $axisMin; $v <= $axisMax + 0.0001; $v += $step)
            <line x1="{{ $left }}" x2="{{ $left + $plotW }}" y1="{{ $yAt($v) }}" y2="{{ $yAt($v) }}" stroke="{{ abs($v) < 0.0001 ? 'var(--axis)' : 'var(--grid)' }}" stroke-width="1"/>
            <text x="{{ $left - 8 }}" y="{{ $yAt($v) + 4 }}" text-anchor="end" class="axis-label">{{ $compact($v) }}</text>
        @endfor
        @foreach($labels as $i => $label)
            @if($n <= 12 || $i % (int) ceil($n / 12) === 0 || $i === $n - 1)
                <text x="{{ $xAt($i) }}" y="{{ $H - 8 }}" text-anchor="middle" class="axis-label">{{ $label }}</text>
            @endif
        @endforeach
        @foreach($series as $k => $s)
            <polyline fill="none" stroke="{{ $slots[$k] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
                      @if(!empty($s['dashed'])) stroke-dasharray="6 4" @endif
                      points="{{ collect($s['values'])->filter(fn ($v) => $v !== null)->map(fn ($v, $i) => round($xAt($i), 1).','.round($yAt($v), 1))->join(' ') }}"/>
            @php $last = $lastIndex($s['values']); @endphp
            @if(collect($s['values'])->contains(fn ($v) => $v !== null))
            <circle cx="{{ $xAt($last) }}" cy="{{ $yAt($s['values'][$last] ?? 0) }}" r="4" fill="{{ $slots[$k] }}" stroke="var(--surface)" stroke-width="2"/>
            <text x="{{ $xAt($last) + 10 }}" y="{{ $endY[$k] + 4 }}" class="value-label">{{ $s['name'] }} {{ $compact($s['values'][$last] ?? 0) }}</text>
            @endif
        @endforeach
        @foreach($labels as $i => $label)
            @php $tipText = $label."\n".collect($series)->map(fn ($s) => $s['name'].': '.$fmt($s['values'][$i] ?? 0))->join("\n"); @endphp
            <rect class="hit" x="{{ $xAt($i) - max($dx, 20) / 2 }}" y="{{ $top }}" width="{{ max($dx, 20) }}" height="{{ $plotH }}" fill="transparent" tabindex="0" data-tip="{{ $tipText }}" aria-label="{{ str_replace("\n", ', ', $tipText) }}"/>
            <g class="crosshair">
                <line x1="{{ $xAt($i) }}" x2="{{ $xAt($i) }}" y1="{{ $top }}" y2="{{ $base }}" stroke="var(--axis)" stroke-dasharray="3 3"/>
                @foreach($series as $k => $s)@if(($s['values'][$i] ?? null) !== null)<circle cx="{{ $xAt($i) }}" cy="{{ $yAt($s['values'][$i]) }}" r="4" fill="{{ $slots[$k] }}" stroke="var(--surface)" stroke-width="2"/>@endif @endforeach
            </g>
        @endforeach
    </svg>
    <details>
        <summary>View as table</summary>
        <table class="table table-sm mt-2 mb-0">
            <thead><tr><th>Period</th>@foreach($series as $s)<th class="text-end">{{ $s['name'] }}</th>@endforeach</tr></thead>
            <tbody>@foreach($labels as $i => $label)<tr><td>{{ $label }}</td>@foreach($series as $s)<td class="text-end">{{ ($s['values'][$i] ?? null) === null ? '—' : number_format((float) $s['values'][$i], 2) }}</td>@endforeach</tr>@endforeach</tbody>
        </table>
    </details>
</div>

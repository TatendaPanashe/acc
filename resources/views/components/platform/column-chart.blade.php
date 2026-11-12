@props(['points', 'money' => true, 'name' => 'Value', 'height' => 280, 'width' => 760])
{{-- Single-series column chart: earlier periods in the de-emphasis step, current period in the accent.
     Labels only on the current and the peak column; every value is in the tooltip and the table view. --}}
@use('App\Support\PlatformFormat', 'F')
@php
    $points = collect($points)->values();
    $W = $width; $H = $height; $left = 56; $right = 12; $top = 30; $bottom = 30;
    $plotW = $W - $left - $right; $plotH = $H - $top - $bottom; $base = $top + $plotH;
    [$axisMax, $step] = F::axis((float) $points->max('value'));
    $slot = $points->count() ? $plotW / $points->count() : $plotW;
    $barW = min(24, $slot * 0.55);
    $peak = $points->search(fn ($p) => $p['value'] == $points->max('value'));
    $fmt = fn ($v) => $money ? F::money($v) : number_format($v);
    $tick = fn ($v) => $money ? F::compact($v, '$') : F::compact($v);
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" class="column-chart" role="img" aria-label="{{ $name }} by month, {{ $points->first()['label'] ?? '' }} to {{ $points->last()['label'] ?? '' }}">
    @for($v = 0; $v <= $axisMax + 0.0001; $v += $step)
        @php $y = $base - ($v / $axisMax) * $plotH; @endphp
        <line x1="{{ $left }}" x2="{{ $W - $right }}" y1="{{ $y }}" y2="{{ $y }}" stroke="{{ $v == 0 ? 'var(--axis)' : 'var(--grid)' }}" stroke-width="1"/>
        <text x="{{ $left - 10 }}" y="{{ $y + 4 }}" text-anchor="end" class="axis-label">{{ $tick($v) }}</text>
    @endfor
    @foreach($points as $i => $point)
        @php
            $cx = $left + $slot * $i + $slot / 2;
            $h = $axisMax > 0 ? ($point['value'] / $axisMax) * $plotH : 0;
            $x = $cx - $barW / 2; $y = $base - $h; $r = min(4, $h);
            $current = $point['current'] ?? false;
            $tip = $point['label'].': '.$fmt($point['value']).($point['tip'] ?? '');
        @endphp
        @if($h > 0)
            <path d="M{{ $x }},{{ $base }} L{{ $x }},{{ $y + $r }} Q{{ $x }},{{ $y }} {{ $x + $r }},{{ $y }} L{{ $x + $barW - $r }},{{ $y }} Q{{ $x + $barW }},{{ $y }} {{ $x + $barW }},{{ $y + $r }} L{{ $x + $barW }},{{ $base }} Z"
                  fill="{{ $current ? 'var(--bar-current)' : 'var(--bar-history)' }}" class="bar"/>
        @endif
        @if($current || $i === $peak)
            <text x="{{ $cx }}" y="{{ $y - 8 }}" text-anchor="middle" class="value-label">{{ $fmt($point['value']) }}</text>
        @endif
        <text x="{{ $cx }}" y="{{ $H - 8 }}" text-anchor="middle" class="axis-label {{ $current ? 'current' : '' }}">{{ $point['label'] }}</text>
        <rect x="{{ $left + $slot * $i }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent" class="hit" tabindex="0" data-tip="{{ $tip }}" aria-label="{{ $tip }}"/>
    @endforeach
</svg>
<details class="table-view">
    <summary>View as table</summary>
    <div class="table-wrap"><table class="data"><thead><tr><th>Month</th><th class="num">{{ $name }}</th>{{ $extraHead ?? '' }}</tr></thead><tbody>
        @foreach($points as $point)<tr><td>{{ $point['label'] }}</td><td class="num">{{ $fmt($point['value']) }}</td>@foreach($point['extra'] ?? [] as $cell)<td class="num">{{ $cell }}</td>@endforeach</tr>@endforeach
    </tbody></table></div>
</details>

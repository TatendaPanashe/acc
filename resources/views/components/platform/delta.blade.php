@props(['change', 'upIsGood' => true, 'vs' => null])
@php
    if ($change === null) { $tone = 'flat'; }
    elseif ($change == 0) { $tone = 'flat'; }
    else { $tone = ($change > 0) === $upIsGood ? 'good' : 'bad'; }
    $text = $change === null ? 'New' : ($change == 0 ? 'No change' : rtrim(rtrim(number_format(abs($change), 1), '0'), '.').'%');
    $direction = $change === null || $change == 0 ? 'steady' : ($change > 0 ? 'up' : 'down');
@endphp
<span class="delta {{ $tone }}" aria-label="{{ $direction }} {{ $text }}">
    @if($change !== null && $change != 0)<x-platform.icon :name="$change > 0 ? 'arrow-up' : 'arrow-down'" size="15"/>@endif{{ $text }}
</span>@if($vs)<span class="vs">vs {{ $vs }}</span>@endif

@props(['rows', 'empty' => 'No data for this period.'])
{{-- Ranked horizontal bars, one series: the value sits at the bar tip; share is in the tooltip. --}}
@php $rows = collect($rows); @endphp
@php $max = (float) $rows->max('amount'); @endphp
@if($rows->isEmpty())
    <p class="muted" style="margin:0">{{ $empty }}</p>
@else
    <ul class="ranked">
        @foreach($rows as $row)
            @php $tip = $row['label'].': '.\App\Support\PlatformFormat::money($row['amount']).' ('.rtrim(rtrim(number_format($row['share'], 1), '0'), '.').'% of revenue)'; @endphp
            <li tabindex="0" data-tip="{{ $tip }}" aria-label="{{ $tip }}">
                <span class="ranked-label">{{ $row['label'] }}</span>
                <span class="ranked-track"><span class="ranked-bar" style="width: calc((100% - 96px) * {{ $max > 0 ? max(0.015, round($row['amount'] / $max, 4)) : 0 }})"></span><span class="ranked-value">{{ \App\Support\PlatformFormat::money($row['amount']) }}</span></span>
            </li>
        @endforeach
    </ul>
@endif

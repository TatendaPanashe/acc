@props(['label', 'value', 'icon', 'info' => null, 'change' => 'none', 'upIsGood' => true, 'vs' => null, 'href' => null])
<div class="card stat-tile">
    <div class="stat-top">
        <div class="stat-label">{{ $label }} @if($info)<span class="info-dot" tabindex="0" data-tip="{{ $info }}" aria-label="{{ $info }}"><x-platform.icon name="info" size="15"/></span>@endif</div>
        <span class="tile-icon"><x-platform.icon :name="$icon" size="22"/></span>
    </div>
    <div class="stat-value">@if($href)<a href="{{ $href }}">{{ $value }}</a>@else{{ $value }}@endif</div>
    <div class="stat-foot">
        @if($change !== 'none')<x-platform.delta :change="$change" :up-is-good="$upIsGood" :vs="$vs"/>@endif
        {{ $slot }}
    </div>
</div>

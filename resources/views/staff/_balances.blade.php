<div class="balances">
    @foreach($balances as $b)
        <div class="card balance">
            <div class="muted small">{{ $b['type']->name }}{{ $b['type']->paid || str_contains(strtolower($b['type']->name), 'unpaid') ? '' : ' (unpaid)' }}</div>
            @if($b['entitlement'] !== null)
                <div class="stat">{{ rtrim(rtrim(number_format($b['remaining'], 2), '0'), '.') }} <span class="small muted">of {{ rtrim(rtrim(number_format($b['entitlement'], 2), '0'), '.') }} days left</span></div>
                <div class="bar" role="img" aria-label="{{ $b['taken'] }} days taken of {{ $b['entitlement'] }}"><span style="width: {{ $b['entitlement'] > 0 ? min(100, round(($b['taken'] + $b['pending']) / $b['entitlement'] * 100)) : 0 }}%"></span></div>
                <div class="small muted">{{ rtrim(rtrim(number_format($b['taken'], 2), '0'), '.') }} taken{{ $b['pending'] ? ' · '.rtrim(rtrim(number_format($b['pending'], 2), '0'), '.').' awaiting approval' : '' }}</div>
            @else
                <div class="stat">{{ rtrim(rtrim(number_format($b['taken'], 2), '0'), '.') }} <span class="small muted">days taken this year</span></div>
                <div class="small muted">{{ $b['pending'] ? rtrim(rtrim(number_format($b['pending'], 2), '0'), '.').' awaiting approval · ' : '' }}granted case by case</div>
            @endif
        </div>
    @endforeach
</div>

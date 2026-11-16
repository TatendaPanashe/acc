@props(['rows' => [], 'total' => null, 'totalLabel' => 'Total', 'head' => ['Description', 'Amount']])
@if($rows)
<table class="items" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td class="items-head">{{ $head[0] }}</td><td class="items-head" style="text-align: right;">{{ $head[1] }}</td></tr>
@foreach($rows as [$description, $amount])
<tr><td class="items-cell">{{ $description }}</td><td class="items-amount">{{ $amount }}</td></tr>
@endforeach
@if($total !== null)
<tr><td class="items-total-label">{{ $totalLabel }}</td><td class="items-total">{{ $total }}</td></tr>
@endif
</table>
@endif

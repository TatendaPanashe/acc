@props(['rows' => [], 'total' => null, 'totalLabel' => 'Total', 'head' => ['Description', 'Amount']])
@foreach($rows as [$description, $amount])
- {{ $description }}: {{ $amount }}
@endforeach
@if($total !== null)
{{ $totalLabel }}: {{ $total }}
@endif


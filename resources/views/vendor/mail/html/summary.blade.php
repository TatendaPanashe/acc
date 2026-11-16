@props(['rows' => [], 'title' => null])
@php($rows = array_filter($rows, fn ($v) => $v !== null && $v !== ''))
@if($rows)
<table class="summary" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@if($title)
<tr><td class="summary-title" colspan="2">{{ $title }}</td></tr>
@endif
@foreach($rows as $label => $value)
<tr class="{{ $loop->last ? 'summary-last' : '' }}"><td class="summary-label">{{ $label }}</td><td class="summary-value">{{ $value }}</td></tr>
@endforeach
</table>
@endif

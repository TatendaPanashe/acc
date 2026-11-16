@props(['rows' => [], 'title' => null])
@php($rows = array_filter($rows, fn ($v) => $v !== null && $v !== ''))
@if($rows)
@if($title)
{{ $title }}
@endif
@foreach($rows as $label => $value)
- {{ $label }}: {{ $value }}
@endforeach

@endif

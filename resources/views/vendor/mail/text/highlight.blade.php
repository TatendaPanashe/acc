@props(['label', 'value', 'note' => null, 'tone' => 'primary'])
{{ strtoupper($label) }}: {{ $value }}@if($note) ({{ $note }})@endif


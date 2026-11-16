@props(['brand' => []])
{{ $brand['name'] ?? config('app.name') }}

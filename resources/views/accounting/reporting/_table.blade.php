{{-- Renders a report result: columns [key => [label, format]], rows [cells, style, link]. --}}
@php
    $fmt = function ($value, string $format) {
        if ($value === null || $value === '') return '';
        return match ($format) {
            'money' => number_format((float) $value, 2),
            'percent' => number_format((float) $value, 1).'%',
            'number' => rtrim(rtrim(number_format((float) $value, 4), '0'), '.'),
            'date' => \Illuminate\Support\Carbon::parse($value)->format('d M Y'),
            default => $value,
        };
    };
    $firstKey = array_key_first($report['columns']);
@endphp
<table class="table table-sm report-table mb-0">
    <thead><tr>@foreach($report['columns'] as [$label, $format])<th class="{{ in_array($format, ['money', 'percent', 'number']) ? 'text-end' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($report['rows'] as $row)
        @php $style = $row['style'] ?? null; @endphp
        <tr class="{{ $style === 'heading' ? 'table-light fw-bold' : ($style === 'total' ? 'fw-bold border-top border-dark' : ($style === 'subtotal' ? 'fw-semibold' : '')) }}">
            @foreach($report['columns'] as $key => [$label, $format])
                @php $value = $row['cells'][$key] ?? null; @endphp
                <td class="{{ in_array($format, ['money', 'percent', 'number']) ? 'text-end text-nowrap' : '' }} {{ in_array($format, ['money']) && is_numeric($value) && $value < 0 ? 'text-danger' : '' }}">
                    @if($key === $firstKey && !empty($row['link']) && empty($print))
                        <a href="{{ $row['link'] }}">{{ $fmt($value, $format) }}</a>
                    @else
                        {{ $fmt($value, $format) }}
                    @endif
                </td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($report['columns']) }}" class="text-muted">No data for this selection.</td></tr>
    @endforelse
    </tbody>
</table>
@foreach($report['notes'] as $note)<p class="small text-muted mt-2 mb-0">{{ $note }}</p>@endforeach

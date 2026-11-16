@props(['brand' => []])
<tr>
<td class="header">
<a href="{{ $brand['url'] ?? config('app.url') }}" style="display: inline-block; text-decoration: none;">
<table class="brand" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@if(!empty($brand['logo']))
<td><img src="{{ $brand['logo'] }}" class="brand-logo" alt="{{ $brand['name'] }}"></td>
@else
<td><span class="brand-mark">{{ $brand['initials'] ?? 'A' }}</span></td>
<td><span class="brand-name">{{ $brand['name'] ?? config('app.name') }}</span></td>
@endif
</tr>
</table>
</a>
</td>
</tr>

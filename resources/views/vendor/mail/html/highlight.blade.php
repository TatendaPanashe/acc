@props(['label', 'value', 'note' => null, 'tone' => 'primary'])
<table class="highlight highlight-{{ $tone }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="highlight-cell">
<p class="highlight-label">{{ $label }}</p>
<p class="highlight-value">{{ $value }}</p>
@if($note)
<p class="highlight-note">{{ $note }}</p>
@endif
</td>
</tr>
</table>

@props(['title' => null, 'text' => '', 'tone' => 'info'])
<table class="note note-{{ $tone }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="note-cell">
@if($title)
<p class="note-title">{{ $title }}</p>
@endif
<p class="note-body">{!! nl2br(e($text)) !!}</p>
</td>
</tr>
</table>

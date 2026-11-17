@props(['id' => 'password', 'name' => null, 'label' => 'Password', 'placeholder' => 'Your password', 'autocomplete' => 'current-password', 'autofocus' => false, 'minlength' => null, 'describedby' => null])
@php $name ??= $id; @endphp
<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    <div class="control">
        <svg class="lead" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <input id="{{ $id }}" name="{{ $name }}" type="password" placeholder="{{ $placeholder }}" required autocomplete="{{ $autocomplete }}"
               @if($autofocus) autofocus @endif @if($minlength) minlength="{{ $minlength }}" @endif
               @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @else @if($describedby) aria-describedby="{{ $describedby }}" @endif @enderror>
        <button type="button" class="toggle" data-toggle-password="{{ $id }}" aria-label="Show password" aria-pressed="false">
            <svg class="eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
    </div>
    @error($name)<div class="error-text" id="{{ $id }}-error">{{ $message }}</div>@enderror
</div>

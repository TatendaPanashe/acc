@props(['label' => 'Email', 'placeholder' => 'name@company.co.zw'])
<div class="field">
    <label for="email">{{ $label }}</label>
    <div class="control">
        <svg class="lead" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
        <input id="email" name="email" type="email" inputmode="email" value="{{ old('email') }}" placeholder="{{ $placeholder }}" required autofocus autocomplete="username"
               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
    </div>
    @error('email')<div class="error-text" id="email-error">{{ $message }}</div>@enderror
</div>

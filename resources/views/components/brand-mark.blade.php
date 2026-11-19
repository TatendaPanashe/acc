@props(['size' => 32, 'tile' => false])
{{-- Kodomo Technologies cloud mark. Use tile on dark or coloured backgrounds. --}}
<img src="{{ asset('assets/images/kodomo-mark.png') }}" width="{{ $size }}" height="{{ $size }}" alt="" aria-hidden="true"
     {{ $attributes->merge(['style' => 'display:inline-block;object-fit:contain;flex:none;'.($tile ? 'background:#fff;border-radius:8px;padding:3px;box-sizing:border-box;' : '')]) }}>

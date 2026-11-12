@once
@push('styles')
<style>
    .ac-chart { --series-1: #2a78d6; --series-2: #eb6834; --series-3: #1baf7a; --ink: #0b0b0b; --ink-2: #52514e; --muted-ink: #898781;
        --grid: #e1e0d9; --axis: #c3c2b7; --surface: #fcfcfb; position: relative; }
    body.dark .ac-chart { --series-1: #3987e5; --series-2: #d95926; --series-3: #199e70; --ink: #f0efec; --ink-2: #c3c2b7;
        --grid: #2c2c2a; --axis: #383835; --surface: #1a1a19; }
    .ac-chart svg { width: 100%; height: auto; display: block; overflow: visible; }
    .ac-chart .axis-label { font-size: 11px; fill: var(--muted-ink); }
    .ac-chart .value-label { font-size: 11px; font-weight: 600; fill: var(--ink); }
    .ac-chart .bar-label { font-size: 12px; fill: var(--ink-2); }
    .ac-chart .legend { display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; color: var(--ink-2); margin-bottom: 6px; }
    .ac-chart .legend i { display: inline-block; width: 12px; height: 3px; border-radius: 2px; vertical-align: middle; margin-right: 6px; }
    .ac-chart .hit { cursor: crosshair; outline: none; }
    .ac-chart .hit:hover + .crosshair, .ac-chart .hit:focus + .crosshair { opacity: 1; }
    .ac-chart .crosshair { opacity: 0; pointer-events: none; }
    .ac-chart-tip { position: fixed; z-index: 2000; pointer-events: none; background: #0b0b0b; color: #fff; font-size: 12px; line-height: 1.5;
        padding: 6px 9px; border-radius: 6px; white-space: pre; display: none; box-shadow: 0 4px 14px rgba(0,0,0,.18); }
    .ac-chart details summary { cursor: pointer; font-size: 12px; color: var(--muted-ink); margin-top: 4px; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    var tip = document.createElement('div'); tip.className = 'ac-chart-tip'; document.body.appendChild(tip);
    function show(e) { var t = e.target.closest('[data-tip]'); if (!t) return; tip.textContent = t.dataset.tip; tip.style.display = 'block'; move(e); }
    function move(e) {
        var r = e.target.getBoundingClientRect(), x = e.clientX || r.left + r.width / 2, y = e.clientY || r.top;
        tip.style.left = Math.min(x + 14, window.innerWidth - tip.offsetWidth - 8) + 'px'; tip.style.top = Math.max(8, y - tip.offsetHeight - 10) + 'px';
    }
    function hide() { tip.style.display = 'none'; }
    document.addEventListener('mouseover', function (e) { if (e.target.closest('.ac-chart [data-tip]')) show(e); });
    document.addEventListener('mousemove', function (e) { if (e.target.closest('.ac-chart [data-tip]')) move(e); });
    document.addEventListener('mouseout', function (e) { if (e.target.closest('.ac-chart [data-tip]')) hide(); });
    document.addEventListener('focusin', function (e) { if (e.target.closest('.ac-chart [data-tip]')) show(e); });
    document.addEventListener('focusout', hide);
})();
</script>
@endpush
@endonce

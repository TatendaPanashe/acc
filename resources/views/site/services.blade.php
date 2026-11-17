@extends('layouts.site')

@section('title', 'Services and pricing')
@section('description', 'Everything acc263 includes: general ledger, invoicing, payables, inventory, POS, bank reconciliation, multi-currency, tax, fixed assets, reports and team controls. Plans and pricing.')

@section('styles')
    .page-hero { padding: 64px 0 40px; background: linear-gradient(180deg, var(--blue-soft), #fff); }
    .jump { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 24px; }
    .jump a { text-decoration: none; color: var(--ink); background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 6px 14px; font-size: 14px; }
    .jump a:hover { border-color: var(--blue); color: var(--blue); }
    .module { display: grid; grid-template-columns: 64px minmax(0, 1fr); gap: 20px; padding: 32px 0; border-bottom: 1px solid var(--line); scroll-margin-top: 90px; }
    .module:last-child { border-bottom: 0; }
    .module .icon-badge { width: 56px; height: 56px; margin: 0; }
    .module h2 { font-size: 26px; margin-bottom: 4px; }
    .module .ticks { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .toggle { display: inline-flex; background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 4px; gap: 4px; margin: 20px 0 8px; }
    .toggle label { padding: 8px 16px; border-radius: 9px; cursor: pointer; font-weight: 600; font-size: 14px; color: var(--muted); }
    .toggle input { position: absolute; opacity: 0; pointer-events: none; }
    .toggle input:checked + label { background: #fff; color: var(--blue); box-shadow: 0 1px 3px rgba(22,32,51,.12); }
    .toggle input:focus-visible + label { outline: 3px solid rgba(25,87,194,.35); }
    .plans { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; margin-top: 24px; text-align: left; }
    .plan { display: flex; flex-direction: column; }
    .plan.featured { border-color: var(--blue); box-shadow: 0 0 0 2px var(--blue) inset; }
    .plan .badge { align-self: flex-start; font-size: 12px; font-weight: 700; color: #fff; background: var(--blue); border-radius: 999px; padding: 3px 10px; margin-bottom: 10px; }
    .plan .amount { font-size: 38px; font-weight: 800; letter-spacing: -0.02em; margin: 6px 0 0; }
    .plan .per { color: var(--muted); font-size: 14px; }
    .plan .ticks { margin: 18px 0 24px; }
    .plan .btn { margin-top: auto; }
    .compare { width: 100%; border-collapse: collapse; margin-top: 40px; font-size: 15px; }
    .compare th, .compare td { padding: 12px 14px; border-bottom: 1px solid var(--line); text-align: center; }
    .compare th:first-child, .compare td:first-child { text-align: left; }
    .compare thead th { font-size: 15px; position: sticky; top: 68px; background: #fff; }
    .compare .group td { background: var(--bg); font-weight: 700; text-align: left; font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }
    .yes { color: var(--teal); font-weight: 700; } .no { color: #b7c0cc; }
    .table-wrap { overflow-x: auto; }
    .module.has-preview > div { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 32px; align-items: start; }
    .module.has-preview .ticks { grid-template-columns: minmax(0, 1fr); }
    .doc-preview { margin: 0; }
    .doc-thumb { display: block; width: 100%; padding: 0; border: 1px solid var(--line); border-radius: 12px; background: #fff; cursor: zoom-in; overflow: hidden; box-shadow: 0 10px 30px rgba(22, 32, 51, .10); transition: transform .15s, box-shadow .15s; }
    .doc-thumb:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(22, 32, 51, .16); }
    .doc-thumb:focus-visible { outline: 3px solid rgba(25, 87, 194, .4); outline-offset: 3px; }
    .doc-thumb img { display: block; width: 100%; height: auto; }
    .doc-preview figcaption { font-size: 13px; color: var(--muted); margin-top: 10px; text-align: center; }
    .doc-preview .doc-actions { display: flex; gap: 8px; justify-content: center; margin-top: 10px; flex-wrap: wrap; }
    .doc-preview .doc-actions .btn { padding: 8px 14px; font-size: 14px; }
    dialog.pdf-viewer { width: min(920px, 94vw); height: min(92vh, 1200px); padding: 0; border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 30px 80px rgba(0, 0, 0, .35); }
    dialog.pdf-viewer::backdrop { background: rgba(15, 23, 42, .6); }
    .pdf-viewer .bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--line); background: var(--bg); }
    .pdf-viewer .bar strong { font-size: 15px; }
    .pdf-viewer .bar .btn { padding: 7px 12px; font-size: 14px; }
    .pdf-viewer iframe { display: block; width: 100%; height: calc(100% - 59px); border: 0; background: #525659; }
    @media (max-width: 980px) { .plans { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 900px) { .module.has-preview > div { grid-template-columns: minmax(0, 1fr); } .doc-preview { max-width: 320px; } }
    @media (max-width: 700px) { .plans, .module .ticks { grid-template-columns: minmax(0, 1fr); } .module { grid-template-columns: minmax(0, 1fr); } }
@endsection

@section('content')
<div class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Services</span>
        <h1>Everything your finance team needs</h1>
        <p class="lead">From the first invoice to the year-end statements, acc263 covers the full cycle. Each part posts to the same ledger, so nothing is entered twice.</p>
        <nav class="jump" aria-label="Jump to a service">
            @foreach(config('site.modules') as $key => [$title])<a href="#{{ $key }}">{{ $title }}</a>@endforeach
            <a href="#pricing"><strong>Pricing</strong></a>
        </nav>
    </div>
</div>

<section style="padding-top:24px">
    <div class="wrap">
        @foreach(config('site.modules') as $key => $module)
            @php [$title, $icon, $summary, $points] = $module; $preview = $module[4] ?? null; @endphp
            <article class="module {{ $preview ? 'has-preview' : '' }}" id="{{ $key }}">
                <span class="icon-badge {{ $loop->odd ? '' : 'teal' }}"><x-platform.icon :name="$icon" size="26"/></span>
                <div>
                    <div>
                        <h2>{{ $title }}</h2>
                        <p class="muted" style="margin:0">{{ $summary }}</p>
                        <ul class="ticks">@foreach($points as $point)<li>{{ $point }}</li>@endforeach</ul>
                    </div>
                    @if($preview)
                        <figure class="doc-preview">
                            <button type="button" class="doc-thumb" data-pdf="{{ asset($preview['pdf']) }}" data-title="{{ $preview['label'] }}" aria-label="Preview the {{ strtolower($preview['label']) }} (PDF)">
                                <img src="{{ asset($preview['image']) }}" alt="{{ $preview['label'] }}: a fiscal tax invoice with ZIMRA QR code and verification code" loading="lazy" width="1100" height="1556">
                            </button>
                            <figcaption>{{ $preview['label'] }}, fictional data</figcaption>
                            <div class="doc-actions">
                                <a class="btn btn-ghost" href="{{ asset($preview['pdf']) }}" target="_blank" rel="noopener">Open PDF</a>
                                <a class="btn btn-ghost" href="{{ asset($preview['pdf']) }}" download>Download</a>
                            </div>
                        </figure>
                    @endif
                </div>
            </article>
        @endforeach
        <dialog class="pdf-viewer" id="pdf-viewer" aria-labelledby="pdf-viewer-title">
            <div class="bar">
                <strong id="pdf-viewer-title">Sample</strong>
                <div style="display:flex; gap:8px">
                    <a class="btn btn-ghost" id="pdf-viewer-open" href="#" target="_blank" rel="noopener">Open in new tab</a>
                    <button type="button" class="btn btn-ghost" id="pdf-viewer-close" aria-label="Close preview">Close</button>
                </div>
            </div>
            <iframe title="PDF preview" id="pdf-viewer-frame"></iframe>
        </dialog>
    </div>
</section>

<section class="section-alt" id="pricing" style="scroll-margin-top:68px">
    <div class="wrap center">
        <span class="eyebrow">Pricing</span>
        <h2>Plans for every stage</h2>
        <p class="lead">Every plan starts with a 14-day free trial. Prices in USD.</p>
        <div class="toggle" role="radiogroup" aria-label="Billing period">
            <input type="radio" name="cycle" id="m" value="monthly" checked><label for="m">Monthly</label>
            <input type="radio" name="cycle" id="a" value="annual"><label for="a">Yearly · 2 months free</label>
        </div>

        @php
            $labels = collect(config('billing.features'));
            $order = array_flip(array_keys(config('billing.features')));
            $limit = fn ($v, $noun) => $v === null ? 'Custom '.$noun : $v.' '.str($noun)->singular()->plural($v);
            $previous = null;
        @endphp
        <div class="plans">
            @foreach($plans as $plan)
                @php
                    $own = $plan->features->whereNull('limit_value')->pluck('feature_key')->reject(fn ($k) => in_array($k, ['users', 'branches', 'companies']))->sortBy(fn ($k) => $order[$k] ?? 999)->values();
                    $new = $previous ? $own->diff($previous->features->pluck('feature_key')) : $own;
                @endphp
                <div class="card plan {{ $plan->is_featured ? 'featured' : '' }}">
                    @if($plan->is_featured)<span class="badge">Most popular</span>@else<span class="eyebrow" style="margin-bottom:10px; min-height:1.2em">{{ $plan->tagline }}</span>@endif
                    <h3>{{ $plan->name }}</h3>
                    <p>{{ $plan->description }}</p>
                    <div class="amount"><span class="price" data-monthly="{{ number_format((float) $plan->monthly_price, 0) }}" data-annual="{{ number_format((float) $plan->annual_price, 0) }}">{{ $plan->is_custom ? 'From ' : '' }}${{ number_format((float) $plan->monthly_price, 0) }}</span></div>
                    <div class="per" data-monthly="per month" data-annual="per year">per month</div>
                    <ul class="ticks">
                        @if($previous)<li><strong>Everything in {{ $previous->name }}</strong></li>@endif
                        @foreach($new as $key)<li>{{ $labels[$key][0] ?? $key }}</li>@endforeach
                        <li>{{ $limit($plan->limit('users'), 'users') }}</li>
                        <li>{{ $limit($plan->limit('branches'), 'branches') }}</li>
                        <li>{{ $limit($plan->limit('companies'), 'companies') }}</li>
                    </ul>
                    @if($plan->is_custom)
                        <a href="{{ route('site.contact', ['topic' => 'enterprise']) }}" class="btn btn-ghost">Talk to us</a>
                    @else
                        <a href="{{ route('register', ['plan' => $plan->code]) }}" class="btn {{ $plan->is_featured ? 'btn-primary' : 'btn-ghost' }}">Start free trial</a>
                    @endif
                </div>
                @php $previous = $plan; @endphp
            @endforeach
        </div>

        <div class="table-wrap">
            <table class="compare">
                <caption class="muted" style="caption-side:top; text-align:left; padding-bottom:8px">Compare plans</caption>
                <thead><tr><th scope="col">Feature</th>@foreach($plans as $plan)<th scope="col">{{ $plan->name }}</th>@endforeach</tr></thead>
                <tbody>
                    <tr class="group"><td colspan="{{ $plans->count() + 1 }}">Limits</td></tr>
                    @foreach(['users', 'branches', 'companies'] as $metric)
                        <tr><td>{{ $labels[$metric][0] }}</td>@foreach($plans as $plan)<td>{{ $plan->limit($metric) ?? 'Custom' }}</td>@endforeach</tr>
                    @endforeach
                    <tr class="group"><td colspan="{{ $plans->count() + 1 }}">Features</td></tr>
                    @foreach($labels->filter(fn ($f) => $f[1] === 'feature') as $key => [$label])
                        <tr><td>{{ $label }}</td>@foreach($plans as $plan)<td>@if($plan->hasFeature($key))<span class="yes" aria-label="Included">✓</span>@else<span class="no" aria-label="Not included">—</span>@endif</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="cta-band">
            <div><h2>Not sure which plan?</h2><p>Start on any plan; you can change it at any time.</p></div>
            <div style="display:flex; gap:10px; flex-wrap:wrap"><a href="{{ route('register') }}" class="btn btn-light btn-lg">Start free trial</a><a href="{{ route('site.contact') }}" class="btn btn-lg" style="color:#fff; border-color:rgba(255,255,255,.6)">Ask a question</a></div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var dialog = document.getElementById('pdf-viewer');
    if (!dialog) return;
    var frame = document.getElementById('pdf-viewer-frame');
    document.querySelectorAll('.doc-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            // Phones rarely show PDFs inside a page: open the file itself there.
            if (!dialog.showModal || window.matchMedia('(max-width: 700px)').matches) {
                window.open(thumb.dataset.pdf, '_blank', 'noopener');
                return;
            }
            document.getElementById('pdf-viewer-title').textContent = thumb.dataset.title;
            document.getElementById('pdf-viewer-open').href = thumb.dataset.pdf;
            frame.src = thumb.dataset.pdf + '#view=FitH';
            dialog.showModal();
        });
    });
    document.getElementById('pdf-viewer-close').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('close', function () { frame.src = 'about:blank'; });
})();
document.querySelectorAll('input[name=cycle]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        var c = this.value;
        document.querySelectorAll('.price').forEach(function (el) { el.textContent = el.textContent.replace(/\$[\d,]+/, '$' + el.dataset[c]); });
        document.querySelectorAll('.per').forEach(function (el) { el.textContent = el.dataset[c]; });
    });
});
</script>
@endpush

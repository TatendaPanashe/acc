@extends('layouts.main')

@section('title', 'Help Centre')

@push('styles')
<style>
    .help-hero { background: linear-gradient(135deg, #1957c2, #0f3f91 60%, #0f766e); color: #fff; border-radius: 16px; padding: 36px 32px; margin-bottom: 24px; position: relative; overflow: hidden; }
    .help-hero h2 { color: #fff; font-size: 28px; font-weight: 800; margin: 0 0 6px; letter-spacing: -.01em; }
    .help-hero p { margin: 0 0 20px; opacity: .88; max-width: 640px; }
    .help-search { position: relative; max-width: 560px; }
    .help-hero .help-search input[type=search] { width: 100%; height: 50px; border-radius: 12px !important; border: 0 !important; padding: 0 18px 0 46px !important; font-size: 16px;
        background: #fff !important; color: #142033 !important; box-shadow: 0 10px 30px -10px rgba(0,0,0,.35) !important; }
    .help-hero .help-search input[type=search]:focus { outline: 3px solid rgba(255,255,255,.55); outline-offset: 2px; }
    .help-search svg { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #5b6677; z-index: 1; }
    .help-hero .meta { margin-top: 14px; font-size: 13px; opacity: .8; }
    .help-step { display: flex; gap: 14px; align-items: flex-start; padding: 14px; border: 1px solid #e3e8ef; border-radius: 12px; height: 100%; color: inherit; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
    .help-step:hover { border-color: #1957c2; box-shadow: 0 6px 18px -10px rgba(25,87,194,.5); text-decoration: none; }
    .help-step .num { flex: none; width: 34px; height: 34px; border-radius: 50%; background: #e8f0fc; color: #1957c2; font-weight: 700; display: grid; place-items: center; }
    .help-cat-head { display: flex; align-items: center; gap: 12px; margin-bottom: 4px; }
    .help-cat-icon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; background: #e8f0fc; color: #1957c2; flex: none; }
    .help-guide { display: flex; justify-content: space-between; gap: 12px; padding: 12px 0; border-top: 1px solid #edf0f5; }
    .help-guide:first-child { border-top: 0; }
    .help-guide a.title { font-weight: 600; }
    .help-guide small { display: block; color: #6b7685; }
    .help-dl { display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; border-top: 1px solid #edf0f5; }
    .help-dl:first-of-type { border-top: 0; }
    .help-dl .ico { flex: none; width: 38px; height: 38px; border-radius: 10px; background: #e3f4f1; color: #0f766e; display: grid; place-items: center; font-size: 11px; font-weight: 800; }
    .no-results { display: none; }
    body.dark .help-step, body.dark .help-guide, body.dark .help-dl { border-color: rgba(255,255,255,.08); }
    body.dark .help-step .num, body.dark .help-cat-icon { background: rgba(57,135,229,.18); }
</style>
@endpush

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Help Centre', 'section' => 'Help'])

    <div class="help-hero">
        <h2>How can we help?</h2>
        <p>Step-by-step guides for every part of acc263: setting up your company, daily accounting, payroll and the portals. Read them here or download them as PDFs.</p>
        <div class="help-search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" id="help-search" placeholder="Search guides, e.g. payroll, VAT, bank reconciliation" aria-label="Search guides" autocomplete="off">
        </div>
        <div class="meta">{{ $guideCount }} guides · updated with every release</div>
    </div>

    <div id="help-start" class="card">
        <div class="header"><h2><strong>Start here</strong>: setting up a new workspace</h2></div>
        <div class="body">
            <div class="row g-3">
                @foreach($startHere as $i => $guide)
                    <div class="col-md-6 col-xl-3">
                        <a class="help-step" href="{{ route('help.show', $guide['slug']) }}">
                            <span class="num">{{ $i + 1 }}</span>
                            <span><strong class="d-block">{{ $guide['title'] }}</strong><small class="text-muted">{{ \Illuminate\Support\Str::limit($guide['summary'], 90) }}</small></span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            @foreach($categories as $category)
                <div class="card help-category">
                    <div class="body">
                        <div class="help-cat-head">
                            <span class="help-cat-icon"><i data-feather="{{ $category['icon'] }}"></i></span>
                            <div><h5 class="mb-0">{{ $category['title'] }}</h5><small class="text-muted">{{ $category['intro'] }}</small></div>
                        </div>
                        <div class="mt-2">
                            @foreach($category['guides'] as $guide)
                                <div class="help-guide" data-search="{{ strtolower($guide['title'].' '.$guide['summary'].' '.collect($guide['headings'])->pluck('title')->join(' ')) }}">
                                    <div>
                                        <a class="title" href="{{ route('help.show', $guide['slug']) }}">{{ $guide['title'] }}</a>
                                        <small>{{ $guide['summary'] }}</small>
                                    </div>
                                    <div class="text-end text-nowrap small">
                                        <span class="text-muted d-block">{{ $guide['minutes'] }} min read</span>
                                        <a href="{{ route('help.pdf', $guide['slug']) }}" aria-label="Download {{ $guide['title'] }} as PDF">PDF ↓</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="card no-results" id="help-no-results"><div class="body text-muted">No guides match your search. Try another word, or download the full manual.</div></div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="header"><h2><strong>Downloads</strong></h2></div>
                <div class="body">
                    <div class="help-dl"><span class="ico">PDF</span><div><a href="{{ route('help.manual') }}" class="fw-semibold">Complete user manual</a><small class="d-block text-muted">Every guide in one document, with a contents page.</small></div></div>
                    <div class="help-dl"><span class="ico">PDF</span><div><a href="{{ route('help.checklist') }}" class="fw-semibold">Setup checklist</a><small class="d-block text-muted">Tick-box list of everything to configure before you go live.</small></div></div>
                    @foreach($templates as $key => [$title, $description])
                        <div class="help-dl"><span class="ico">CSV</span><div><a href="{{ route('help.template', $key) }}" class="fw-semibold">{{ $title }}</a><small class="d-block text-muted">{{ $description }}</small></div></div>
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="header"><h2><strong>Still stuck?</strong></h2></div>
                <div class="body small">
                    <p>Check <a href="{{ route('help.show', 'troubleshooting') }}">Troubleshooting</a> first. Your Company Admin manages users, roles and settings; for anything else, contact us.</p>
                    <a href="{{ route('site.contact') }}" class="btn btn-outline-primary btn-sm">Contact support</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('help-search');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase(), any = false;
        document.querySelectorAll('.help-category').forEach(function (card) {
            var visible = 0;
            card.querySelectorAll('.help-guide').forEach(function (row) {
                var show = !q || row.dataset.search.indexOf(q) > -1;
                row.style.display = show ? '' : 'none';
                visible += show ? 1 : 0;
            });
            card.style.display = visible ? '' : 'none';
            any = any || visible > 0;
        });
        document.getElementById('help-start').style.display = q ? 'none' : '';
        document.getElementById('help-no-results').style.display = any ? 'none' : 'block';
    });
})();
</script>
@endpush

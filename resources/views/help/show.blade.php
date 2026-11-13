@extends('layouts.main')

@section('title', $guide['title'].' · Help')

@push('styles')
<style>
    .guide-body { font-size: 15px; line-height: 1.7; max-width: 820px; }
    .guide-body h3 { font-size: 19px; font-weight: 700; margin: 30px 0 10px; padding-top: 6px; scroll-margin-top: 80px; }
    .guide-body h4 { font-size: 16px; font-weight: 700; margin: 22px 0 8px; }
    .guide-body p, .guide-body ul, .guide-body ol { margin-bottom: 14px; }
    .guide-body li { margin-bottom: 6px; }
    .guide-body ul, .guide-body ul > li { list-style: disc !important; } .guide-body ol, .guide-body ol > li { list-style: decimal !important; }
    .guide-body ul, .guide-body ol { padding-left: 24px; } .guide-body li { display: list-item !important; }
    .guide-body ul ul, .guide-body ul ul > li, .guide-body ol ul, .guide-body ol ul > li { list-style: disc !important; }
    .guide-body ul ul { list-style: circle; margin-top: 6px; } .guide-body li > p { margin-bottom: 6px; }
    .guide-body table { width: 100%; border-collapse: collapse; margin: 14px 0 20px; font-size: 14px; }
    .guide-body th, .guide-body td { border: 1px solid #e1e6ee; padding: 8px 10px; vertical-align: top; text-align: left; }
    .guide-body th { background: #f5f7fb; }
    .guide-body code { background: #f1f4f9; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
    .guide-toc { position: sticky; top: 84px; }
    .guide-toc a { display: block; padding: 6px 10px; border-left: 2px solid #e3e8ef; color: #4b5563; font-size: 14px; }
    .guide-toc a:hover { border-left-color: #1957c2; color: #1957c2; text-decoration: none; }
    .guide-nav a { display: block; padding: 14px 16px; border: 1px solid #e3e8ef; border-radius: 12px; }
    .guide-nav a:hover { border-color: #1957c2; text-decoration: none; }
    body.dark .guide-body th { background: rgba(255,255,255,.05); }
    body.dark .guide-body th, body.dark .guide-body td, body.dark .guide-nav a { border-color: rgba(255,255,255,.1); }
    body.dark .guide-body code { background: rgba(255,255,255,.08); }
    @media print { .guide-actions, .guide-toc, .guide-nav, #leftsidebar, .navbar { display: none !important; } }
</style>
@endpush

@section('content')
    @include('accounting.partials.page-header', ['title' => $guide['title'], 'section' => 'Help Centre'])

    <div class="row">
        <div class="{{ count($guide['headings']) > 1 ? 'col-xl-9' : 'col-xl-12' }}">
            <div class="card">
                <div class="body">
                    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start mb-3 guide-actions">
                        <div>
                            <a href="{{ route('help.index') }}" class="small">← Help Centre</a>
                            @if($category)<span class="small text-muted"> · {{ $category['title'] }}</span>@endif
                            <h3 class="mt-1 mb-1" style="font-weight:800">{{ $guide['title'] }}</h3>
                            <small class="text-muted">Section {{ $guide['number'] }} of the user manual · {{ $guide['minutes'] }} min read</small>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            @if($screen)<a href="{{ $screen['url'] }}" class="btn btn-primary btn-sm">{{ $screen['label'] }}</a>@endif
                            <a href="{{ route('help.pdf', $guide['slug']) }}" class="btn btn-outline-primary btn-sm">Download PDF</a>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
                        </div>
                    </div>
                    <div class="guide-body">{!! $html !!}</div>
                </div>
            </div>

            <div class="row g-3 guide-nav mb-4">
                <div class="col-md-6">@if($previous)<a href="{{ route('help.show', $previous['slug']) }}"><small class="text-muted d-block">← Previous</small><strong>{{ $previous['title'] }}</strong></a>@endif</div>
                <div class="col-md-6 text-md-end">@if($next)<a href="{{ route('help.show', $next['slug']) }}"><small class="text-muted d-block">Next →</small><strong>{{ $next['title'] }}</strong></a>@endif</div>
            </div>
        </div>
        @if(count($guide['headings']) > 1)
        <div class="col-xl-3 d-none d-xl-block">
                <nav class="card guide-toc" aria-label="On this page">
                    <div class="body">
                        <small class="text-uppercase text-muted fw-semibold d-block mb-2">On this page</small>
                        @foreach($guide['headings'] as $heading)<a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>@endforeach
                    </div>
                </nav>
        </div>
        @endif
    </div>
@endsection

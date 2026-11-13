<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>acc263 · {{ $title }}</title>
    <style>
        @page { margin: 22mm 18mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.55; color: #1b2433; }
        .cover { text-align: left; padding-top: 70px; }
        .cover .brand { font-size: 22px; font-weight: bold; color: #1957c2; }
        .cover h1 { font-size: 28px; margin: 30px 0 8px; }
        .cover p { color: #5b6677; font-size: 12px; }
        .band { height: 6px; background: #1957c2; margin: 18px 0 0; width: 120px; }
        h2.section { font-size: 18px; color: #12408f; border-bottom: 2px solid #1957c2; padding-bottom: 6px; margin: 0 0 12px; }
        h3 { font-size: 13px; margin: 16px 0 6px; color: #1b2433; }
        h4 { font-size: 11.5px; margin: 12px 0 4px; }
        p, ul, ol { margin: 0 0 8px; } li { margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: 9.5px; }
        th, td { border: 1px solid #cfd6e0; padding: 4px 6px; vertical-align: top; text-align: left; }
        th { background: #eef2f8; }
        code { font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; background: #f1f4f9; padding: 1px 3px; }
        .page-break { page-break-before: always; }
        .contents td { border: 0; border-bottom: 1px dotted #cfd6e0; padding: 5px 0; font-size: 11px; }
        .muted { color: #6b7685; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8.5px; color: #8a94a3; }
    </style>
</head>
<body>
    <footer>acc263 · {{ $title }} · generated {{ now()->format('d M Y') }}</footer>

    @if($withContents)
        <div class="cover">
            <div class="brand">acc263</div>
            <h1>{{ $title }}</h1>
            <p>How the system works and how to set it up: every guide from the Help Centre.<br>Generated {{ now()->format('d F Y') }}.</p>
            <div class="band"></div>
        </div>
        <div class="page-break"></div>
        <h2 class="section">Contents</h2>
        <table class="contents">
            @foreach($sections as $section)<tr><td style="width:40px">{{ $section['number'] }}</td><td>{{ $section['title'] }}</td></tr>@endforeach
        </table>
    @endif

    @foreach($sections as $i => $section)
        @if($withContents || $i > 0)<div class="page-break"></div>@endif
        <h2 class="section">{{ $section['number'] }}. {{ $section['title'] }}</h2>
        {!! $section['html'] !!}
    @endforeach
</body>
</html>

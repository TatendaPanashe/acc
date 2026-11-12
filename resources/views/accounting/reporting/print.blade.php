<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }} · {{ auth()->user()->company?->name }}</title>
    <style>
        body { font: 12px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; margin: 24px; background: #fff; }
        h1 { font-size: 18px; margin: 0; } .meta { color: #555; margin: 4px 0 16px; }
        table { width: 100%; border-collapse: collapse; } th, td { padding: 4px 6px; border-bottom: 1px solid #e3e3e3; text-align: left; }
        th { border-bottom: 2px solid #333; } .text-end { text-align: right; } .text-nowrap { white-space: nowrap; }
        .table-light { background: #f3f4f6; } .fw-bold { font-weight: 700; } .fw-semibold { font-weight: 600; }
        .border-top { border-top: 2px solid #333; } .text-danger { color: #b42318; } .small, small { font-size: 11px; color: #555; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">Print</button></p>
    <h1>{{ auth()->user()->company?->name }} · {{ $report['title'] }}</h1>
    <p class="meta">{{ $report['subtitle'] }} · amounts in base currency · printed {{ now()->format('d M Y H:i') }} by {{ auth()->user()->name }}</p>
    @include('accounting.reporting._table', ['report' => $report, 'print' => true])
</body>
</html>

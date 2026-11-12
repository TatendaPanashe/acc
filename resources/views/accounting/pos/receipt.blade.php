<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $sale->receipt_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { background: #f4f5f7; color: #111; font-family: Arial, sans-serif; margin: 0; padding: 18px; }
        .actions { margin: 0 auto 12px; max-width: 320px; display: flex; gap: 8px; }
        .actions a, .actions button { border: 1px solid #d0d5dd; background: #fff; color: #111; border-radius: 4px; padding: 8px 10px; text-decoration: none; cursor: pointer; font-size: 13px; }
        .receipt { background: #fff; margin: 0 auto; width: 80mm; min-height: 120mm; padding: 5mm; font-family: "Courier New", monospace; font-size: 11px; line-height: 1.25; box-shadow: 0 8px 30px rgba(15, 23, 42, .12); }
        .center { text-align: center; }
        .right { text-align: right; }
        .line { border-top: 1px dashed #111; margin: 7px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .muted { color: #444; }
        .item { margin-bottom: 5px; }
        .item-name { overflow-wrap: anywhere; }
        .totals .row { margin: 2px 0; }
        .grand { font-size: 14px; font-weight: 700; }
        @page { size: 80mm auto; margin: 0; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .receipt { box-shadow: none; margin: 0; width: 80mm; padding: 4mm; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Print Receipt</button>
        <a href="{{ route('accounting.pos.show', $sale) }}">Back</a>
        <a href="{{ route('accounting.pos.create') }}">New Sale</a>
    </div>

    <main class="receipt">
        <div class="center">
            <strong>{{ strtoupper($sale->company?->name ?? config('app.name')) }}</strong><br>
            <span class="muted">STANDARD TAX INVOICE</span><br>
            <span>{{ $sale->warehouse?->name }} {{ $sale->warehouse?->location }}</span>
        </div>
        <div class="line"></div>
        <div class="row"><span>Receipt:</span><strong>{{ $sale->receipt_number }}</strong></div>
        <div class="row"><span>Date:</span><span>{{ $sale->sold_at?->format('Y-m-d H:i') }}</span></div>
        <div class="row"><span>Cashier:</span><span>{{ $sale->cashier?->name ?? 'POS' }}</span></div>
        <div class="row"><span>Customer:</span><span>{{ $sale->customer?->name ?? 'Walk-in' }}</span></div>
        <div class="line"></div>

        @foreach($sale->lines as $line)
            <div class="item">
                <div class="item-name">{{ $line->description }}</div>
                <div class="row"><span>{{ number_format($line->quantity, 2) }} x {{ number_format($line->unit_price, 2) }}</span><span>{{ number_format($line->line_total + $line->tax_amount, 2) }}</span></div>
                @if((float) $line->discount > 0)
                    <div class="row muted"><span>Discount</span><span>-{{ number_format($line->discount, 2) }}</span></div>
                @endif
            </div>
        @endforeach

        <div class="line"></div>
        <div class="totals">
            <div class="row"><span>Subtotal</span><span>{{ number_format($sale->subtotal, 2) }}</span></div>
            <div class="row"><span>Discount</span><span>{{ number_format($sale->discount_total, 2) }}</span></div>
            <div class="row"><span>VAT/Tax</span><span>{{ number_format($sale->tax_total, 2) }}</span></div>
            <div class="row grand"><span>TOTAL {{ $sale->currency }}</span><span>{{ number_format($sale->total, 2) }}</span></div>
            <div class="row"><span>Tendered</span><span>{{ number_format($sale->amount_tendered, 2) }}</span></div>
            <div class="row"><span>Change</span><span>{{ number_format($sale->change_due, 2) }}</span></div>
            <div class="row"><span>Payment</span><span>{{ strtoupper(str_replace('_', ' ', $sale->payment_method)) }}</span></div>
        </div>
        <div class="line"></div>
        <div class="center">
            Thank you for shopping with us<br>
            Keep this receipt for returns<br>
            {{ now()->format('Y-m-d H:i') }}
        </div>
    </main>
</body>
</html>

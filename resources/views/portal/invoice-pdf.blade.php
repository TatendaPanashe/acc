<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 24px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #172033;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }
        .top {
            border-bottom: 2px solid #172033;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .brand {
            display: inline-block;
            width: 58%;
            vertical-align: top;
        }
        .brand-mark {
            background: #1957c2;
            color: #fff;
            display: inline-block;
            font-weight: 700;
            letter-spacing: .5px;
            padding: 8px 10px;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        .invoice-meta {
            display: inline-block;
            width: 41%;
            text-align: right;
            vertical-align: top;
        }
        h1 {
            font-size: 28px;
            letter-spacing: 1px;
            margin: 0 0 8px;
        }
        h2 {
            font-size: 12px;
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }
        .muted { color: #667085; }
        .pill {
            background: #eef4ff;
            border: 1px solid #c7d7fe;
            border-radius: 999px;
            color: #174ea6;
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 9px;
            text-transform: uppercase;
        }
        .info-grid {
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 16px;
            table-layout: fixed;
            width: 100%;
        }
        .info-grid td {
            border: 0;
            padding: 0;
            vertical-align: top;
            width: 50%;
        }
        .info-grid .gap {
            width: 10px;
        }
        .box {
            border: 1px solid #d9e2ec;
            border-radius: 6px;
            min-height: 86px;
            padding: 11px;
            width: 100%;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th {
            background: #172033;
            color: #fff;
            font-size: 10px;
            letter-spacing: .4px;
            padding: 8px 7px;
            text-align: left;
            text-transform: uppercase;
        }
        td {
            border-bottom: 1px solid #e7edf5;
            padding: 7px;
            vertical-align: top;
        }
        .num { text-align: right; white-space: nowrap; }
        .totals {
            margin-top: 14px;
            margin-left: auto;
            width: 260px;
        }
        .totals td {
            border: 0;
            padding: 5px 0;
        }
        .total-row td {
            border-top: 2px solid #172033;
            font-size: 14px;
            font-weight: 700;
            padding-top: 8px;
        }
        .footer {
            border-top: 1px solid #d9e2ec;
            color: #667085;
            font-size: 9px;
            margin-top: 16px;
            padding-top: 9px;
        }
    </style>
</head>
<body>
    <div class="top">
        <div class="brand">
            <div class="brand-mark">acc263</div>
            <div><strong>{{ $company->name ?? 'Finance Office' }}</strong></div>
            <div class="muted">Modern finance workspace</div>
        </div>
        <div class="invoice-meta">
            <h1>INVOICE</h1>
            <div><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="muted">Issued {{ $invoice->invoice_date?->format('M d, Y') }} · Due {{ $invoice->due_date?->format('M d, Y') }}</div>
            <div style="margin-top: 8px;"><span class="pill">{{ str_replace('_', ' ', $invoice->status) }}</span></div>
        </div>
    </div>

    <table class="info-grid">
        <tr>
            <td>
                <div class="box">
                    <h2>Billed To</h2>
                    <strong>{{ $customer->name }}</strong><br>
                    <span class="muted">{{ $customer->email }}</span><br>
                    <span class="muted">{{ $customer->phone }}</span><br>
                    <span>{{ $customer->billing_address }}</span>
                </div>
            </td>
            <td class="gap"></td>
            <td>
                <div class="box">
                    <h2>Summary</h2>
                    <table>
                        <tr><td class="muted">Currency</td><td class="num">{{ $invoice->currency }}</td></tr>
                        <tr><td class="muted">Subtotal</td><td class="num">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                        <tr><td class="muted">Tax</td><td class="num">{{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
                        <tr><td class="muted">Balance</td><td class="num"><strong>{{ number_format((float) $invoice->balance, 2) }}</strong></td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit</th>
                <th class="num">Tax %</th>
                <th class="num">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->lines->take(10) as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td class="num">{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $line->tax_rate, 2) }}</td>
                    <td class="num">{{ number_format((float) $line->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td>Invoice total</td>
                    <td class="num">1.00</td>
                    <td class="num">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                    <td class="num">{{ $invoice->subtotal > 0 ? number_format(((float) $invoice->tax_total / (float) $invoice->subtotal) * 100, 2) : '0.00' }}</td>
                    <td class="num">{{ number_format((float) $invoice->total, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td class="muted">Tax</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
        <tr><td class="muted">Paid</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
        <tr class="total-row"><td>Balance Due</td><td class="num">{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</td></tr>
    </table>

    <div class="footer">
        Generated from the acc263 client portal. Payment updates submitted through the portal remain subject to finance review and posting.
    </div>
</body>
</html>

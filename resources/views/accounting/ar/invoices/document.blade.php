{{--
    Sales invoice document (A4, dompdf). One layout for staff downloads, the customer portal and emailed PDFs.
    Covers a VAT tax invoice (seller and buyer names, addresses, TIN and VAT numbers; serial number and date;
    description, quantity and value of each supply; VAT per line and per rate; totals) and, once fiscalised,
    ZIMRA FDMS fiscal details (device, fiscal day, receipt numbers, verification code and QR).

    Expects: $invoice (with lines), $customer, $company, $einvoice (EinvoiceDocument|null), optional $context ('staff'|'portal').
--}}
@php
    $context = $context ?? 'staff';
    $cur = $invoice->currency;
    $money = fn ($v) => number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';

    $fiscal = $einvoice && $einvoice->authority_reference;
    $fp = fn (string $key) => $einvoice ? data_get($einvoice->payload, $key) : null;
    $draft = $invoice->status === 'draft';
    $vatRegistered = $fiscal || filled($company?->vat_number);
    $title = $draft ? 'Draft Invoice' : ($fiscal ? 'Fiscal Tax Invoice' : ($vatRegistered ? 'Tax Invoice' : 'Invoice'));

    $sellerName = $fp('taxpayer_name') ?: ($company?->name ?? config('app.name'));
    $sellerTin = $fp('taxpayer_tin') ?: $company?->tin;
    $sellerVat = $fp('vat_number') ?: $company?->vat_number;

    // Lines: amounts on the invoice are before VAT; VAT is worked out per line at its rate.
    $lines = $invoice->lines->values()->map(function ($line) {
        $net = round((float) $line->line_total, 2);
        $rate = (float) $line->tax_rate;

        return (object) ['description' => $line->description, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price,
            'rate' => $rate, 'net' => $net, 'vat' => round($net * $rate / 100, 2)];
    });
    if ($lines->isEmpty()) {
        $lines = collect([(object) ['description' => 'Invoice total', 'quantity' => 1, 'unit_price' => $invoice->subtotal,
            'rate' => (float) $invoice->subtotal > 0 ? (float) $invoice->tax_total / (float) $invoice->subtotal * 100 : 0, 'net' => (float) $invoice->subtotal, 'vat' => (float) $invoice->tax_total]]);
    }
    $vatSummary = $lines->groupBy(fn ($l) => (string) $l->rate)->map(fn ($g, $rate) => (object) [
        'rate' => (float) $rate, 'net' => $g->sum('net'), 'vat' => $g->sum('vat'),
    ])->sortByDesc('rate')->values();

    $balance = (float) $invoice->balance;
    $overdue = !$draft && $balance > 0 && $invoice->due_date && $invoice->due_date->lt(today());
    $stamp = match (true) {
        $invoice->status === 'paid' || (!$draft && $balance <= 0) => ['Paid', 'paid'],
        $overdue => ['Overdue', 'overdue'],
        $invoice->status === 'part_paid' => ['Part paid', 'part'],
        default => null,
    };
    $terms = $invoice->due_date && $invoice->invoice_date ? (int) round($invoice->invoice_date->diffInDays($invoice->due_date)) : null;

    $logo = null;
    if ($company?->logo_path && is_file($path = storage_path('app/public/'.$company->logo_path))) {
        $logo = 'data:'.(mime_content_type($path) ?: 'image/png').';base64,'.base64_encode(file_get_contents($path));
    }

    $bank = $company?->bankAccounts()->where('status', true)
        ->orderByRaw('CASE WHEN currency = ? THEN 0 ELSE 1 END', [$cur])->orderByDesc('is_primary')->first();

    $qrData = $fp('response.qrData');
    $qrImage = $fiscal ? \App\Modules\Accounting\Services\ZimraFdmsService::qrImage($qrData) : null;
    $verificationCode = $fiscal ? ($fp('verification_code') ?? \App\Modules\Accounting\Services\ZimraFdmsService::verificationCode($qrData)) : null;
    $verifyHost = $qrData ? (parse_url($qrData, PHP_URL_SCHEME).'://'.parse_url($qrData, PHP_URL_HOST)) : null;
    $fiscalisedAt = $fp('submitted_at') ? \Illuminate\Support\Carbon::parse($fp('submitted_at'))->timezone(config('app.timezone')) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $title }} {{ $invoice->invoice_number }}</title>
<style>
    @page { size: A4 portrait; margin: 34px 36px 56px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #1f2937; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; line-height: 1.45; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; padding: 0; }
    .muted { color: #6b7280; }
    .num { text-align: right; white-space: nowrap; }
    .strong { font-weight: bold; color: #111827; }
    .label { color: #6b7280; font-size: 7.5px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }

    .accent { height: 5px; background: #1e3a8a; margin-bottom: 18px; }

    .seller-name { font-size: 15px; font-weight: bold; color: #111827; margin: 6px 0 3px; }
    .logo { max-height: 56px; max-width: 190px; }
    .doc-title { font-size: 21px; font-weight: bold; color: #1e3a8a; text-align: right; letter-spacing: .4px; text-transform: uppercase; }
    .doc-number { font-size: 11px; font-weight: bold; text-align: right; margin: 2px 0 8px; color: #111827; }
    .meta td { padding: 2px 0; }
    .meta .k { color: #6b7280; text-align: right; padding-right: 10px; }
    .meta .v { text-align: right; font-weight: bold; color: #111827; width: 96px; }

    .stamp { display: inline-block; border: 1.5px solid; border-radius: 3px; padding: 2px 8px; font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
    .stamp.paid { color: #067647; border-color: #067647; }
    .stamp.overdue { color: #b42318; border-color: #b42318; }
    .stamp.part { color: #b54708; border-color: #b54708; }

    .parties { margin: 18px 0 16px; }
    .party { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; }
    .party .name { font-size: 11px; font-weight: bold; color: #111827; margin: 3px 0 2px; }
    .ids td { padding: 1px 0; font-size: 9px; }
    .ids .k { color: #6b7280; width: 62px; }
    .fiscal-box { border: 1px solid #c7d2fe; background: #f5f7ff; border-radius: 4px; padding: 10px 12px; }

    .items th { background: #1e3a8a; color: #fff; font-size: 7.5px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; padding: 7px 6px; text-align: left; }
    .items th.num { text-align: right; }
    .items td { padding: 7px 6px; border-bottom: 1px solid #eef0f4; }
    .items tr.alt td { background: #fafbfc; }
    .items .idx { color: #9ca3af; width: 16px; }

    .after { margin-top: 14px; }
    .vat-summary th { font-size: 7.5px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; text-align: right; padding: 5px 6px; border-bottom: 1px solid #e5e7eb; }
    .vat-summary th:first-child, .vat-summary td:first-child { text-align: left; }
    .vat-summary td { padding: 5px 6px; text-align: right; border-bottom: 1px solid #f1f3f6; }
    .totals td { padding: 4px 0; }
    .totals .k { color: #4b5563; }
    .totals .grand td { border-top: 1.5px solid #111827; padding-top: 7px; font-size: 11.5px; font-weight: bold; color: #111827; }
    .due-box { background: #1e3a8a; color: #fff; border-radius: 4px; padding: 9px 12px; margin-top: 8px; }
    .due-box .k { font-size: 8px; letter-spacing: .6px; text-transform: uppercase; color: #c7d2fe; }
    .due-box .v { font-size: 15px; font-weight: bold; }

    .section { margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    .verify { border: 1px solid #c7d2fe; border-radius: 4px; padding: 10px 12px; margin-top: 16px; }
    .code { font-family: DejaVu Sans Mono, monospace; font-size: 12px; font-weight: bold; letter-spacing: 1px; color: #111827; }
    .qr { width: 96px; height: 96px; }

    .watermark { position: fixed; top: 38%; left: 8%; width: 84%; text-align: center; font-size: 84px; font-weight: bold; color: rgba(180, 35, 24, 0.09); transform: rotate(-28deg); letter-spacing: 8px; }
    .page-footer { position: fixed; bottom: -36px; left: 0; right: 0; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 7.5px; color: #9ca3af; }
</style>
</head>
<body>
@if($draft)<div class="watermark">DRAFT</div>@endif

<div class="page-footer">
    <table><tr>
        <td>{{ $sellerName }}@if($sellerTin) · TIN {{ $sellerTin }}@endif @if($sellerVat) · VAT {{ $sellerVat }}@endif · {{ $title }} {{ $invoice->invoice_number }}</td>
        <td class="num" style="width: 90px;"></td>
    </tr></table>
</div>

<div class="accent"></div>

{{-- Seller and document details --}}
<table>
    <tr>
        <td style="width: 55%;">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="{{ $sellerName }}">@endif
            <div class="seller-name">{{ $sellerName }}</div>
            @if($company?->name && strcasecmp(trim($company->name), trim($sellerName)) !== 0)<div class="muted" style="margin-top: -2px;">Trading as {{ $company->name }}</div>@endif
            @if($company?->address)<div>{!! nl2br(e($company->address)) !!}</div>@endif
            <div class="muted">{{ collect([$company?->phone, $company?->email])->filter()->implode(' · ') }}</div>
            <table class="ids" style="margin-top: 6px; width: auto;">
                @if($sellerTin)<tr><td class="k">TIN</td><td class="strong">{{ $sellerTin }}</td></tr>@endif
                @if($sellerVat)<tr><td class="k">VAT No.</td><td class="strong">{{ $sellerVat }}</td></tr>@endif
                @if($company?->registration_number)<tr><td class="k">Reg. No.</td><td>{{ $company->registration_number }}</td></tr>@endif
            </table>
        </td>
        <td style="width: 45%;">
            <div class="doc-title">{{ $title }}</div>
            <div class="doc-number">No. {{ $invoice->invoice_number }}</div>
            <table class="meta">
                <tr><td class="k">Invoice date</td><td class="v">{{ $invoice->invoice_date?->format('d M Y') }}</td></tr>
                @if($invoice->due_date)<tr><td class="k">Due date</td><td class="v">{{ $invoice->due_date->format('d M Y') }}</td></tr>@endif
                @if($terms !== null)<tr><td class="k">Terms</td><td class="v">{{ $terms === 0 ? 'Due on receipt' : $terms.' days' }}</td></tr>@endif
                <tr><td class="k">Currency</td><td class="v">{{ $cur }}</td></tr>
                @if($customer?->customer_number)<tr><td class="k">Customer no.</td><td class="v">{{ $customer->customer_number }}</td></tr>@endif
            </table>
            @if($stamp)<div style="text-align: right; margin-top: 8px;"><span class="stamp {{ $stamp[1] }}">{{ $stamp[0] }}</span></div>@endif
        </td>
    </tr>
</table>

{{-- Buyer, and fiscal or payment summary --}}
<table class="parties">
    <tr>
        <td style="width: 55%; padding-right: 10px;">
            <div class="party">
                <div class="label">Bill to</div>
                <div class="name">{{ $customer?->name }}</div>
                @if($customer?->billing_address)<div>{!! nl2br(e($customer->billing_address)) !!}</div>@endif
                <div class="muted">{{ collect([$customer?->phone, $customer?->email])->filter()->implode(' · ') }}</div>
                @if($customer?->tin || $customer?->vat_number)
                    <table class="ids" style="margin-top: 5px; width: auto;">
                        @if($customer?->tin)<tr><td class="k">TIN</td><td class="strong">{{ $customer->tin }}</td></tr>@endif
                        @if($customer?->vat_number)<tr><td class="k">VAT No.</td><td class="strong">{{ $customer->vat_number }}</td></tr>@endif
                    </table>
                @endif
            </div>
        </td>
        <td style="width: 45%;">
            @if($fiscal)
                <div class="fiscal-box">
                    <div class="label" style="color: #1e3a8a;">ZIMRA fiscal details</div>
                    <table class="ids" style="margin-top: 4px;">
                        <tr><td class="k" style="width: 92px;">Device</td><td class="strong">{{ $fp('device_serial') ? $fp('device_serial').' · ' : '' }}ID {{ $fp('device_id') }}</td></tr>
                        <tr><td class="k">Fiscal day</td><td class="strong">{{ $fp('fiscal_day_no') ?? '—' }}</td></tr>
                        <tr><td class="k">Receipt no.</td><td class="strong">{{ $fp('response.receiptCounter') ?? '—' }} / {{ $fp('response.receiptGlobalNo') ?? '—' }}</td></tr>
                        @if($fiscalisedAt)<tr><td class="k">Fiscalised</td><td class="strong">{{ $fiscalisedAt->format('d M Y H:i') }}</td></tr>@endif
                        <tr><td class="k">ZIMRA receipt ID</td><td class="strong">{{ $einvoice->authority_reference }}</td></tr>
                    </table>
                </div>
            @else
                <div class="party">
                    <div class="label">Amount due</div>
                    <div style="font-size: 17px; font-weight: bold; color: #111827; margin: 3px 0;">{{ $cur }} {{ $money($balance > 0 ? $balance : 0) }}</div>
                    <div class="muted">{{ $balance > 0 && $invoice->due_date ? 'Due by '.$invoice->due_date->format('d M Y') : 'Nothing outstanding' }}</div>
                </div>
            @endif
        </td>
    </tr>
</table>

{{-- Supplies --}}
<table class="items">
    <thead>
        <tr>
            <th class="idx">#</th>
            <th>Description</th>
            <th class="num">Qty</th>
            <th class="num">Unit price</th>
            @if($vatRegistered)
                <th class="num">Amount excl.</th>
                <th class="num">VAT %</th>
                <th class="num">VAT</th>
                <th class="num">Total incl.</th>
            @else
                <th class="num">Amount</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach($lines as $i => $line)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="idx">{{ $i + 1 }}</td>
                <td class="strong" style="font-weight: normal;">{{ $line->description }}</td>
                <td class="num">{{ $qty($line->quantity) }}</td>
                <td class="num">{{ $money($line->unit_price) }}</td>
                @if($vatRegistered)
                    <td class="num">{{ $money($line->net) }}</td>
                    <td class="num">{{ $line->rate > 0 ? $pct($line->rate) : 'Zero' }}</td>
                    <td class="num">{{ $money($line->vat) }}</td>
                    <td class="num strong">{{ $money($line->net + $line->vat) }}</td>
                @else
                    <td class="num strong">{{ $money($line->net + $line->vat) }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>

{{-- VAT analysis and totals --}}
<table class="after">
    <tr>
        <td style="width: 55%; padding-right: 22px;">
            @if($vatRegistered)
                <div class="label" style="margin-bottom: 4px;">VAT summary</div>
                <table class="vat-summary">
                    <tr><th>Rate</th><th>Taxable amount</th><th>VAT</th><th>Total</th></tr>
                    @foreach($vatSummary as $row)
                        <tr><td>{{ $row->rate > 0 ? 'Standard '.$pct($row->rate) : 'Zero-rated 0%' }}</td><td>{{ $money($row->net) }}</td><td>{{ $money($row->vat) }}</td><td>{{ $money($row->net + $row->vat) }}</td></tr>
                    @endforeach
                </table>
            @endif
        </td>
        <td style="width: 45%;">
            <table class="totals">
                @if($vatRegistered || (float) $invoice->tax_total > 0)
                    <tr><td class="k">Subtotal (excl. VAT)</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
                    <tr><td class="k">VAT</td><td class="num">{{ $money($invoice->tax_total) }}</td></tr>
                @endif
                <tr class="grand"><td>Total ({{ $cur }})</td><td class="num">{{ $money($invoice->total) }}</td></tr>
                @if((float) $invoice->amount_paid > 0)
                    <tr><td class="k">Less: amount paid</td><td class="num">({{ $money($invoice->amount_paid) }})</td></tr>
                @endif
            </table>
            <div class="due-box">
                <table><tr>
                    <td><div class="k">Balance due</div>@if($invoice->due_date && $balance > 0)<div style="font-size: 8.5px; color: #e0e7ff;">by {{ $invoice->due_date->format('d M Y') }}</div>@endif</td>
                    <td class="num v">{{ $cur }} {{ $money(max($balance, 0)) }}</td>
                </tr></table>
            </div>
        </td>
    </tr>
</table>

{{-- ZIMRA verification --}}
@if($fiscal)
    <div class="verify">
        <table><tr>
            @if($qrImage)<td style="width: 108px;"><img src="{{ $qrImage }}" class="qr" alt="ZIMRA verification QR code"></td>@endif
            <td>
                <div class="label" style="color: #1e3a8a;">Verify this fiscal invoice</div>
                <div style="margin: 4px 0;">Verification code <span class="code">{{ $verificationCode }}</span></div>
                <div class="muted">Scan the QR code, or verify manually at <strong style="color: #1f2937;">{{ $verifyHost }}</strong> with the device ID, invoice date, receipt global number and verification code above.</div>
                @if($qrData)<div class="muted" style="font-size: 7.5px; margin-top: 3px;">{{ $qrData }}</div>@endif
                @if($einvoice->status === 'submitted')<div style="color: #b54708; margin-top: 3px;">Accepted by ZIMRA with validation warnings.</div>@endif
            </td>
        </tr></table>
    </div>
@elseif($vatRegistered && !$draft)
    <div class="section muted">This invoice has not been fiscalised with ZIMRA FDMS.</div>
@endif

{{-- How to pay --}}
@if($balance > 0 && !$draft && ($bank || $context === 'portal'))
    <div class="section">
        <table><tr>
            <td style="width: 55%; padding-right: 22px;">
                <div class="label" style="margin-bottom: 4px;">How to pay</div>
                @if($bank)
                    <table class="ids">
                        <tr><td class="k" style="width: 90px;">Bank</td><td class="strong">{{ $bank->bank_name }}</td></tr>
                        <tr><td class="k">Account name</td><td class="strong">{{ $bank->account_name }}</td></tr>
                        <tr><td class="k">Account no.</td><td class="strong">{{ $bank->account_number }}{{ $bank->currency ? ' ('.$bank->currency.')' : '' }}</td></tr>
                        @if($bank->branch_code)<tr><td class="k">Branch code</td><td>{{ $bank->branch_code }}</td></tr>@endif
                        @if($bank->swift_code)<tr><td class="k">SWIFT</td><td>{{ $bank->swift_code }}</td></tr>@endif
                    </table>
                @endif
            </td>
            <td style="width: 45%;">
                <div class="label" style="margin-bottom: 4px;">Payment reference</div>
                <div class="strong" style="font-size: 11px;">{{ $invoice->invoice_number }}</div>
                <div class="muted" style="margin-top: 3px;">Please quote the invoice number with your payment{{ $context === 'portal' ? ', and record it in the customer portal so we can match it quickly' : '' }}.</div>
            </td>
        </tr></table>
    </div>
@endif

<div class="section muted" style="text-align: center;">
    Thank you for your business.@if($company?->email) Questions about this invoice? Contact {{ $company->email }}@if($company?->phone) or {{ $company->phone }}@endif.@endif
</div>
</body>
</html>

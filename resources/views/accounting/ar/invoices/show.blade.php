@extends('layouts.main')
@section('title', 'Customer Invoice')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Customer Invoice', 'section' => 'Accounts Receivable'])
<div class="card"><div class="header d-flex justify-content-between align-items-center"><h2><strong>{{ $invoice->invoice_number }}</strong> {{ ucfirst(str_replace('_',' ',$invoice->status)) }}</h2><div class="d-flex gap-2"><a class="btn btn-primary" href="{{ route('accounting.ar.invoices.pdf', $invoice) }}"><i class="fas fa-file-pdf"></i> PDF</a>@if($fiscalActive && $invoice->status !== 'draft' && !$einvoice?->authority_reference)@canroute('accounting.ar.invoices.zimra', 'POST')<form method="POST" action="{{ route('accounting.ar.invoices.zimra', $invoice) }}">@csrf<button class="btn btn-info"><i class="fas fa-paper-plane"></i> {{ $einvoice ? 'Retry ZIMRA' : 'Send to ZIMRA' }}</button></form>@endcanroute @endif @if($invoice->status === 'draft')@canroute('accounting.ar.invoices.post', 'POST')<form method="POST" action="{{ route('accounting.ar.invoices.post', $invoice) }}">@csrf<button class="btn btn-success"><i class="fas fa-check"></i> Post</button></form>@endcanroute @endif</div></div><div class="body">
@if($einvoice)
@php
    $fiscalQrData = data_get($einvoice->payload, 'response.qrData');
    $fiscalQr = \App\Modules\Accounting\Services\ZimraFdmsService::qrImage($fiscalQrData);
    $fiscalCode = data_get($einvoice->payload, 'verification_code') ?? \App\Modules\Accounting\Services\ZimraFdmsService::verificationCode($fiscalQrData);
@endphp
<div class="alert {{ $einvoice->authority_reference ? 'alert-success' : 'alert-danger' }} d-flex gap-3 align-items-start">
    @if($fiscalQr)<img src="{{ $fiscalQr }}" alt="ZIMRA verification QR code" style="width:96px;height:96px;background:#fff;padding:4px;border-radius:6px">@endif
    <div>
        @if($einvoice->authority_reference)
            <strong><i class="fas fa-check-circle"></i> Fiscalised with ZIMRA FDMS</strong>{{ $einvoice->status === 'submitted' ? ' (with validation warnings)' : '' }}<br>
            @if($fiscalCode)Verification code: <strong class="font-monospace">{{ $fiscalCode }}</strong> · @endif
            Receipt ID: {{ $einvoice->authority_reference }}
            @if(data_get($einvoice->payload, 'response.receiptGlobalNo')) · Global no: {{ data_get($einvoice->payload, 'response.receiptGlobalNo') }}@endif
            @if(data_get($einvoice->payload, 'fiscal_day_no')) · Fiscal day: {{ data_get($einvoice->payload, 'fiscal_day_no') }}@endif
            @foreach((array) data_get($einvoice->payload, 'response.validationErrors', []) as $v)
                <div class="small mt-1">[{{ $v['validationErrorColor'] ?? '?' }}] {{ $v['validationErrorCode'] ?? '' }} {{ $v['validationErrorDescription'] ?? '' }}</div>
            @endforeach
            @if($fiscalQrData)<div class="small mt-1 text-break">Verify: <a href="{{ $fiscalQrData }}" target="_blank" rel="noopener">{{ $fiscalQrData }}</a></div>@endif
        @else
            <strong><i class="fas fa-exclamation-triangle"></i> Not fiscalised yet</strong><br>
            <span class="small">{{ data_get($einvoice->payload, 'error', 'ZIMRA did not accept the invoice.') }}</span>
        @endif
    </div>
</div>
@elseif($fiscalActive && $invoice->status !== 'draft')
<div class="alert alert-warning">This invoice has not been sent to ZIMRA yet.</div>
@endif
<div class="row mb-3"><div class="col-md-4"><strong>Customer:</strong> {{ $invoice->customer?->name }}</div><div class="col-md-2"><strong>Date:</strong> {{ $invoice->invoice_date?->format('Y-m-d') }}</div><div class="col-md-2"><strong>Due:</strong> {{ $invoice->due_date?->format('Y-m-d') }}</div><div class="col-md-2"><strong>Total:</strong> {{ number_format($invoice->total,2) }}</div><div class="col-md-2"><strong>Balance:</strong> {{ number_format($invoice->balance,2) }}</div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Account</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Tax %</th><th class="text-end">Line</th></tr></thead><tbody>@foreach($invoice->lines as $line)<tr><td>{{ $line->revenueAccount?->code }} - {{ $line->revenueAccount?->name }}</td><td>{{ $line->description }}</td><td class="text-end">{{ number_format($line->quantity,4) }}</td><td class="text-end">{{ number_format($line->unit_price,2) }}</td><td class="text-end">{{ number_format($line->tax_rate,2) }}</td><td class="text-end">{{ number_format($line->line_total,2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="5" class="text-end">Subtotal</th><th class="text-end">{{ number_format($invoice->subtotal,2) }}</th></tr><tr><th colspan="5" class="text-end">Tax</th><th class="text-end">{{ number_format($invoice->tax_total,2) }}</th></tr><tr><th colspan="5" class="text-end">Total</th><th class="text-end">{{ number_format($invoice->total,2) }}</th></tr></tfoot></table></div>
</div></div>
@endsection

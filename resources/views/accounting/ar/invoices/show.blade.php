@extends('layouts.main')
@section('title', 'Customer Invoice')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Customer Invoice', 'section' => 'Accounts Receivable'])
<div class="card"><div class="header d-flex justify-content-between align-items-center"><h2><strong>{{ $invoice->invoice_number }}</strong> {{ ucfirst(str_replace('_',' ',$invoice->status)) }}</h2><div class="d-flex gap-2"><a class="btn btn-primary" href="{{ route('accounting.ar.invoices.pdf', $invoice) }}"><i class="fas fa-file-pdf"></i> PDF</a>@canroute('accounting.ar.invoices.zimra', 'POST')<form method="POST" action="{{ route('accounting.ar.invoices.zimra', $invoice) }}">@csrf<button class="btn btn-info"><i class="fas fa-paper-plane"></i> Send to ZIMRA</button></form>@endcanroute @if($invoice->status === 'draft')@canroute('accounting.ar.invoices.post', 'POST')<form method="POST" action="{{ route('accounting.ar.invoices.post', $invoice) }}">@csrf<button class="btn btn-success"><i class="fas fa-check"></i> Post</button></form>@endcanroute @endif</div></div><div class="body">
@if($einvoice)
<div class="alert alert-info">
    <strong>ZIMRA FDMS:</strong> {{ ucfirst($einvoice->status) }}
    @if($einvoice->authority_reference)
        <span class="ms-2">Receipt ID: {{ $einvoice->authority_reference }}</span>
    @endif
    @if(data_get($einvoice->payload, 'response.receiptGlobalNo'))
        <span class="ms-2">Global No: {{ data_get($einvoice->payload, 'response.receiptGlobalNo') }}</span>
    @endif
    @if(data_get($einvoice->payload, 'response.qrData'))
        <div class="mt-2"><small class="text-break">{{ data_get($einvoice->payload, 'response.qrData') }}</small></div>
    @endif
</div>
@endif
<div class="row mb-3"><div class="col-md-4"><strong>Customer:</strong> {{ $invoice->customer?->name }}</div><div class="col-md-2"><strong>Date:</strong> {{ $invoice->invoice_date?->format('Y-m-d') }}</div><div class="col-md-2"><strong>Due:</strong> {{ $invoice->due_date?->format('Y-m-d') }}</div><div class="col-md-2"><strong>Total:</strong> {{ number_format($invoice->total,2) }}</div><div class="col-md-2"><strong>Balance:</strong> {{ number_format($invoice->balance,2) }}</div></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Account</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Tax %</th><th class="text-end">Line</th></tr></thead><tbody>@foreach($invoice->lines as $line)<tr><td>{{ $line->revenueAccount?->code }} - {{ $line->revenueAccount?->name }}</td><td>{{ $line->description }}</td><td class="text-end">{{ number_format($line->quantity,4) }}</td><td class="text-end">{{ number_format($line->unit_price,2) }}</td><td class="text-end">{{ number_format($line->tax_rate,2) }}</td><td class="text-end">{{ number_format($line->line_total,2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="5" class="text-end">Subtotal</th><th class="text-end">{{ number_format($invoice->subtotal,2) }}</th></tr><tr><th colspan="5" class="text-end">Tax</th><th class="text-end">{{ number_format($invoice->tax_total,2) }}</th></tr><tr><th colspan="5" class="text-end">Total</th><th class="text-end">{{ number_format($invoice->total,2) }}</th></tr></tfoot></table></div>
</div></div>
@endsection

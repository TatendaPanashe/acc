@extends('layouts.main')
@section('title', 'Customer Invoices')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Customer Invoices', 'section' => 'Accounts Receivable'])
<div class="card"><div class="header d-flex justify-content-between align-items-center"><h2><strong>Invoice</strong> Register</h2><a href="{{ route('accounting.ar.invoices.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Invoice</a></div><div class="body table-responsive">
<table class="table table-striped"><thead><tr><th>No.</th><th>Customer</th><th>Date</th><th>Due</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th><th class="text-end">Download</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($invoices as $invoice)
<tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->customer?->name }}</td><td>{{ $invoice->invoice_date?->format('Y-m-d') }}</td><td>{{ $invoice->due_date?->format('Y-m-d') }}</td><td>{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</td><td class="text-end">{{ number_format($invoice->total, 2) }}</td><td class="text-end">{{ number_format($invoice->balance, 2) }}</td><td class="text-end"><a class="btn btn-danger btn-sm" href="{{ route('accounting.ar.invoices.pdf', $invoice) }}"><i class="fas fa-file-pdf"></i> Download</a></td><td class="text-end"><a class="btn btn-light btn-sm" href="{{ route('accounting.ar.invoices.show', $invoice) }}"><i class="fas fa-eye"></i></a>@if($invoice->status === 'draft') <a class="btn btn-primary btn-sm" href="{{ route('accounting.ar.invoices.edit', $invoice) }}"><i class="fas fa-pen"></i></a>@canroute('accounting.ar.invoices.post', 'POST')<form class="d-inline" method="POST" action="{{ route('accounting.ar.invoices.post', $invoice) }}">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Post</button></form>@endcanroute @endif</td></tr>
@empty
<tr><td colspan="9" class="text-muted">No customer invoices have been created.</td></tr>
@endforelse
</tbody></table>{{ $invoices->links() }}</div></div>
@endsection

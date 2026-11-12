@extends('layouts.main')
@section('title', 'Customer Receipts')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Customer Receipts', 'section' => 'Accounts Receivable'])
<div class="row"><div class="col-lg-8"><div class="card"><div class="header"><h2><strong>Receipt</strong> Register</h2></div><div class="body table-responsive"><table class="table table-striped"><thead><tr><th>No.</th><th>Customer</th><th>Date</th><th>Method</th><th>Status</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($receipts as $receipt)<tr><td>{{ $receipt->receipt_number }}</td><td>{{ $receipt->customer?->name }}</td><td>{{ $receipt->receipt_date?->format('Y-m-d') }}</td><td>{{ strtoupper($receipt->payment_method) }}</td><td>{{ ucfirst($receipt->status) }}</td><td class="text-end">{{ number_format($receipt->amount,2) }}</td><td class="text-end">@if($receipt->status === 'draft')@canroute('accounting.ar.receipts.post', 'POST')<form method="POST" action="{{ route('accounting.ar.receipts.post', $receipt) }}">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Post</button></form>@endcanroute @endif</td></tr>@empty<tr><td colspan="7" class="text-muted">No receipts captured.</td></tr>@endforelse
</tbody></table>{{ $receipts->links() }}</div></div></div><div class="col-lg-4"><div class="card"><div class="header"><h2><strong>New</strong> Receipt</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ar.receipts.store') }}">@csrf
<div class="mb-3"><label class="form-label">Customer</label><select name="customer_id" class="form-control" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div>
<div class="mb-3"><label class="form-label">Allocate to Invoice</label><select name="invoice_id" class="form-control"><option value="">Unallocated</option>@foreach($openInvoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} - {{ $invoice->customer?->name }} - {{ number_format($invoice->balance,2) }}</option>@endforeach</select></div>
<div class="mb-3"><label class="form-label">Receipt No.</label><input name="receipt_number" class="form-control" value="RCT-{{ now()->format('Ymd-His') }}" required></div>
<div class="mb-3"><label class="form-label">Date</label><input type="date" name="receipt_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
<div class="mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control" value="USD" required></div>
<div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control text-end" required></div>
<div class="mb-3"><label class="form-label">Method</label><select name="payment_method" class="form-control"><option value="bank">Bank</option><option value="cash">Cash</option><option value="card">Card</option></select></div>
<div class="mb-3"><label class="form-label">Memo</label><textarea name="memo" class="form-control"></textarea></div>
<div class="form-check mb-3"><input type="checkbox" name="post_now" value="1" class="form-check-input" id="post_now"><label class="form-check-label" for="post_now">Post immediately</label></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-primary"><i class="fas fa-save"></i> Save Receipt</button></form></div></div></div></div>
@endsection

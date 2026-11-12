@extends('layouts.main')
@section('title', 'Payment Runs')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Payment Runs', 'section' => 'Accounts Payable'])
<div class="row"><div class="col-lg-8"><div class="card"><div class="header"><h2><strong>Payment</strong> Runs</h2></div><div class="body table-responsive"><table class="table table-striped"><thead><tr><th>No.</th><th>Date</th><th>Method</th><th>Status</th><th>Payments</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
@forelse($runs as $run)<tr><td>{{ $run->run_number }}</td><td>{{ $run->payment_date?->format('Y-m-d') }}</td><td>{{ strtoupper($run->payment_method) }}</td><td>{{ ucfirst($run->status) }}</td><td>{{ $run->payments_count }}</td><td class="text-end">{{ number_format($run->total_amount,2) }}</td><td class="text-end"><a href="{{ route('accounting.ap.payment-runs.show', $run) }}" class="btn btn-light btn-sm"><i class="fas fa-eye"></i></a></td></tr>@empty<tr><td colspan="7" class="text-muted">No payment runs created.</td></tr>@endforelse
</tbody></table>{{ $runs->links() }}</div></div></div><div class="col-lg-4"><div class="card"><div class="header"><h2><strong>New</strong> Payment Run</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ap.payment-runs.store') }}">@csrf
<div class="mb-3"><label class="form-label">Run No.</label><input name="run_number" class="form-control" value="RUN-{{ now()->format('Ymd-His') }}" required></div>
<div class="mb-3"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
<div class="mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control" value="USD" required></div>
<div class="mb-3"><label class="form-label">Method</label><select name="payment_method" class="form-control"><option value="eft">EFT</option><option value="cheque">Cheque</option></select></div>
<label class="form-label">Invoices to Pay</label>
<div class="table-responsive mb-3"><table class="table table-sm"><tbody>@foreach($openInvoices as $invoice)<tr><td><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}"></td><td>{{ $invoice->invoice_number }}<br><small>{{ $invoice->supplier?->name }}</small></td><td class="text-end">{{ number_format($invoice->balance,2) }}</td></tr>@endforeach</tbody></table></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-primary"><i class="fas fa-save"></i> Create Run</button></form></div></div></div></div>
@endsection

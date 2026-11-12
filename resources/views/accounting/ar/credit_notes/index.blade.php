@extends('layouts.main')
@section('title', 'Credit Notes')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Credit Notes', 'section' => 'Accounts Receivable'])
<div class="row"><div class="col-lg-8"><div class="card"><div class="header"><h2><strong>Credit Note</strong> Register</h2></div><div class="body table-responsive"><table class="table table-striped"><thead><tr><th>No.</th><th>Customer</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($creditNotes as $note)<tr><td>{{ $note->credit_note_number }}</td><td>{{ $note->customer?->name }}</td><td>{{ $note->credit_note_date?->format('Y-m-d') }}</td><td>{{ ucfirst($note->status) }}</td><td class="text-end">{{ number_format($note->total,2) }}</td><td class="text-end">@if($note->status === 'draft')@canroute('accounting.ar.credit-notes.post', 'POST')<form method="POST" action="{{ route('accounting.ar.credit-notes.post', $note) }}">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Post</button></form>@else<span class="badge bg-warning text-dark">Awaiting approval</span>@endcanroute @endif</td></tr>@empty<tr><td colspan="6" class="text-muted">No credit notes captured.</td></tr>@endforelse
</tbody></table>{{ $creditNotes->links() }}</div></div></div><div class="col-lg-4"><div class="card"><div class="header"><h2><strong>New</strong> Credit Note</h2></div><div class="body"><form method="POST" action="{{ route('accounting.ar.credit-notes.store') }}">@csrf
<div class="mb-3"><label class="form-label">Customer</label><select name="customer_id" class="form-control" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div>
<div class="mb-3"><label class="form-label">Related Invoice</label><select name="invoice_id" class="form-control"><option value="">None</option>@foreach($invoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} - {{ number_format($invoice->balance,2) }}</option>@endforeach</select></div>
<div class="mb-3"><label class="form-label">Credit Note No.</label><input name="credit_note_number" class="form-control" value="CN-{{ now()->format('Ymd-His') }}" required></div>
<div class="mb-3"><label class="form-label">Date</label><input type="date" name="credit_note_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
<div class="mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control" value="USD" required></div>
<div class="mb-3"><label class="form-label">Subtotal</label><input type="number" step="0.01" name="subtotal" class="form-control text-end" required></div>
<div class="mb-3"><label class="form-label">Tax %</label><input type="number" step="0.01" name="tax_rate" class="form-control text-end" value="15"></div>
<div class="mb-3"><label class="form-label">Reason</label><textarea name="reason" class="form-control"></textarea></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-primary"><i class="fas fa-save"></i> Save Credit</button></form></div></div></div></div>
@endsection

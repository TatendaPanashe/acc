@extends('layouts.main')
@section('title', 'Test payment')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Test payment (sandbox)', 'section' => 'Billing'])
<div class="card"><div class="body">
    <div class="alert alert-warning">This is a test gateway for development. No money moves.</div>
    <p>Invoice <strong>{{ $payment->invoice->number }}</strong> · {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }} · reference {{ $payment->transaction_reference }}</p>
    <form method="POST" action="{{ route('billing.sandbox.complete', $payment) }}" class="d-flex gap-2">
        @csrf
        <button name="outcome" value="success" class="btn btn-success">Simulate successful payment</button>
        <button name="outcome" value="failure" class="btn btn-outline-danger">Simulate failed payment</button>
    </form>
</div></div>
@endsection

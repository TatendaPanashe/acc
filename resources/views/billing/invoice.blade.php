@extends('layouts.main')

@section('title', 'Invoice '.$invoice->number)

@section('content')
@include('accounting.partials.page-header', ['title' => 'Invoice '.$invoice->number, 'section' => 'Billing'])
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="header d-flex justify-content-between align-items-center">
                <h2><strong>{{ $invoice->number }}</strong></h2>
                <span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : 'warning text-dark' }}">{{ $invoice->status === 'pending' ? 'Awaiting payment' : ucfirst($invoice->status) }}</span>
            </div>
            <div class="body">
                <p class="text-muted">Issued {{ $invoice->issued_at->format('d M Y') }} · due {{ $invoice->due_at?->format('d M Y') ?? $invoice->issued_at->format('d M Y') }}</p>
                <table class="table">
                    <tbody>
                    @foreach($invoice->lines ?? [['description' => 'Subscription', 'amount' => (float) $invoice->amount]] as $line)
                        <tr><td>{{ $line['description'] }}</td><td class="text-end">{{ number_format($line['amount'], 2) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><th>Total ({{ $invoice->currency }})</th><th class="text-end">{{ number_format((float) $invoice->amount, 2) }}</th></tr></tfoot>
                </table>
                @if($invoice->payments->isNotEmpty())
                    <h6 class="text-muted text-uppercase small mt-3">Payment attempts</h6>
                    <table class="table table-sm">
                        @foreach($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                                <td>{{ config('platform.payment_providers.'.$payment->provider, ucfirst(str_replace('_', ' ', $payment->provider))) }}</td>
                                <td><span class="badge bg-{{ ['succeeded' => 'success', 'failed' => 'danger'][$payment->status] ?? 'secondary' }}">{{ ucfirst($payment->status) }}</span> <small class="text-muted">{{ $payment->failure_reason }}</small></td>
                                <td class="text-end"><small class="text-muted">{{ $payment->transaction_reference }}</small></td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        @if($invoice->isOpen())
            @if($instructions)
                <div class="card"><div class="header"><h2><strong>Bank transfer</strong> details</h2></div><div class="body">
                    <dl class="row mb-0">@foreach($instructions as $label => $value)@if($value !== '')<dt class="col-5">{{ $label }}</dt><dd class="col-7">{{ $value }}</dd>@endif @endforeach</dl>
                    <p class="text-muted small mt-2 mb-0">Use the payment reference exactly. We activate your subscription when the transfer is confirmed.</p>
                </div></div>
            @endif
            <div class="card">
                <div class="header"><h2><strong>Pay</strong> with</h2></div>
                <div class="body d-grid gap-2">
                    @forelse($gateways as $gateway)
                        <form method="POST" action="{{ route('billing.invoices.pay', $invoice) }}">
                            @csrf <input type="hidden" name="provider" value="{{ $gateway->provider }}">
                            <button class="btn {{ $loop->first ? 'btn-primary' : 'btn-outline-primary' }} w-100">{{ $gateway->display_name }}</button>
                        </form>
                    @empty
                        <p class="text-muted mb-0">No payment methods are available yet. Contact {{ config('billing.sales_email') }}.</p>
                    @endforelse
                </div>
            </div>
        @endif
        <a href="{{ route('billing.index') }}" class="btn btn-secondary">Back to billing</a>
    </div>
</div>
@endsection

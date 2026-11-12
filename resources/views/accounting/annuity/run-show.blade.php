@extends('layouts.main')

@section('title', $run->run_number)

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Billing run '.$run->run_number, 'section' => 'Annuity Billing'])
    @include('accounting.annuity._tabs')

    <div class="card">
        <div class="header"><h2><strong>{{ $run->invoice_count }}</strong> {{ str('invoice')->plural($run->invoice_count) }} · {{ number_format((float) $run->total, 2) }}</h2>
            <small class="text-muted">Run date {{ $run->run_date->format('d M Y') }} by {{ $run->creator?->name ?? 'scheduled run' }}</small></div>
        <div class="body table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Due</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($run->invoices as $invoice)
                    <tr><td><a href="{{ route('accounting.ar.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->customer?->name }}</td><td>{{ $invoice->due_date->format('d M Y') }}</td><td class="text-end">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td><td>{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No invoices were due.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if($run->invoices->where('status', 'draft')->isNotEmpty())<p class="small text-muted mb-0">Draft invoices can be reviewed and posted from Accounts Receivable → Invoices.</p>@endif
        </div>
    </div>
@endsection

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Portal | acc263</title>
    <link href="{{ asset('assets/css/common.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/acc263-uniform.css') }}" rel="stylesheet">
</head>
<body class="acc-app">
    <section class="content" style="margin: 0;">
        <div class="container-fluid">
            <div class="block-header d-flex align-items-center justify-content-between">
                <div>
                    <h2>Client Portal</h2>
                    <p>{{ $customer->name }} - {{ $customer->email }}</p>
                </div>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="btn btn-outline-secondary" type="submit">Log Out</button>
                </form>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="header"><h2><strong>Invoices</strong></h2></div>
                        <div class="body table-responsive">
                            <table class="table table-hover align-middle">
                                <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Total</th><th>Paid</th><th>Balance</th><th>PDF</th><th>Payment Update</th></tr></thead>
                                <tbody>
                                    @forelse($invoices as $invoice)
                                        <tr>
                                            <td>{{ $invoice->invoice_number }}</td>
                                            <td>{{ $invoice->invoice_date?->format('Y-m-d') }}</td>
                                            <td>{{ $invoice->due_date?->format('Y-m-d') }}</td>
                                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</td>
                                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.invoices.pdf', $invoice->id) }}">Download</a>
                                            </td>
                                            <td style="min-width: 280px;">
                                                @if($invoice->balance > 0)
                                                    <form method="POST" action="{{ route('portal.payments.store', $invoice) }}" class="row g-2">
                                                        @csrf
                                                        <div class="col-5"><input name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance }}" class="form-control" placeholder="Amount" required></div>
                                                        <div class="col-4">
                                                            <select name="payment_method" class="form-control">
                                                                <option value="bank">Bank</option>
                                                                <option value="mobile_money">Mobile</option>
                                                                <option value="card">Card</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-3"><button class="btn btn-sm btn-primary w-100" type="submit">Submit</button></div>
                                                        <div class="col-12"><input name="memo" class="form-control" placeholder="Reference or note"></div>
                                                    </form>
                                                @else
                                                    <span class="badge col-green">Paid</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-muted">No invoices are available.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            {{ $invoices->links() }}
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card">
                        <div class="header"><h2><strong>Payment</strong> Updates</h2></div>
                        <div class="body table-responsive">
                            <table class="table table-hover">
                                <thead><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->receipt_number }}</td>
                                            <td>{{ $payment->receipt_date?->format('Y-m-d') }}</td>
                                            <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                                            <td>{{ ucfirst($payment->status) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">No payment updates yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>

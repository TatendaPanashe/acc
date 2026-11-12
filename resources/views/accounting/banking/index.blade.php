@extends('layouts.main')

@section('title', 'Bank Reconciliation')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Bank Reconciliation', 'section' => 'Banking'])

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="header"><h2><strong>Bank</strong> Accounts</h2></div>
                <div class="body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Bank account</th><th>GL account</th></tr></thead>
                        <tbody>
                        @forelse($bankAccounts as $bank)
                            <tr>
                                <td>{{ $bank->label() }} @if($bank->is_primary)<span class="badge bg-secondary">primary</span>@endif</td>
                                <td>
                                    @canroute('accounting.banking.accounts.link', 'PUT')
                                        <form method="POST" action="{{ route('accounting.banking.accounts.link', $bank) }}" class="d-flex gap-2">
                                            @csrf @method('PUT')
                                            <select name="gl_account_id" class="form-control form-control-sm">
                                                <option value="">Select GL account</option>
                                                @foreach($assetAccounts as $account)
                                                    <option value="{{ $account->id }}" @selected($bank->gl_account_id == $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-primary">Link</button>
                                        </form>
                                    @else
                                        {{ $bank->glAccount ? $bank->glAccount->code.' - '.$bank->glAccount->name : 'Not linked' }}
                                    @endcanroute
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">No bank accounts. Add them on the Company Profile.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    <small class="text-muted">Payments and receipts post to the accounts set under Accounting Settings → Account Mapping. Link each bank account to the same GL account it is paid into.</small>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            @canroute('accounting.banking.statements.store', 'POST')
                <div class="card">
                    <div class="header"><h2><strong>Import</strong> Statement (CSV)</h2></div>
                    <div class="body">
                        <form method="POST" action="{{ route('accounting.banking.statements.store') }}" enctype="multipart/form-data" class="row g-2">
                            @csrf
                            <div class="col-12"><label class="form-label">Bank account</label>
                                <select name="company_bank_account_id" class="form-control" required>
                                    @foreach($bankAccounts->whereNotNull('gl_account_id') as $bank)<option value="{{ $bank->id }}">{{ $bank->label() }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Statement date</label><input type="date" name="statement_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                            <div class="col-md-4"><label class="form-label">Opening balance</label><input type="number" step="0.01" name="opening_balance" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">Closing balance</label><input type="number" step="0.01" name="closing_balance" class="form-control" required></div>
                            <div class="col-12"><label class="form-label">CSV file</label><input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
                                <small class="text-muted">Header row with <code>Date</code>, <code>Description</code>, optional <code>Reference</code>, and either <code>Amount</code> (+ in / − out) or <code>Credit</code> and <code>Debit</code> columns. Dates as 2026-01-31 or 31/01/2026.</small>
                            </div>
                            @if($errors->any())<div class="col-12"><div class="alert alert-danger mb-0">{{ $errors->first() }}</div></div>@endif
                            <div class="col-12"><button class="btn btn-success">Import &amp; auto-match</button></div>
                        </form>
                    </div>
                </div>
            @endcanroute
        </div>
    </div>

    <div class="card">
        <div class="header"><h2><strong>Statements</strong></h2></div>
        <div class="body table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Date</th><th>Bank account</th><th class="text-end">Closing</th><th class="text-end">Lines</th><th class="text-end">Unmatched</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($statements as $statement)
                    <tr>
                        <td>{{ $statement->statement_date->format('Y-m-d') }}</td>
                        <td>{{ $statement->bankAccount?->label() ?? $statement->bank_account_name }}</td>
                        <td class="text-end">{{ number_format((float) $statement->closing_balance, 2) }}</td>
                        <td class="text-end">{{ $statement->lines_count }}</td>
                        <td class="text-end">{{ $statement->unmatched_count }}</td>
                        <td><span class="badge {{ $statement->status === 'reconciled' ? 'bg-success' : ($statement->status === 'matched' ? 'bg-info' : 'bg-warning text-dark') }}">{{ ucfirst($statement->status) }}</span></td>
                        <td class="text-end">@if($statement->company_bank_account_id)<a href="{{ route('accounting.banking.statements.show', $statement) }}" class="btn btn-sm btn-primary">Open</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No statements imported yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $statements->links() }}
        </div>
    </div>
@endsection

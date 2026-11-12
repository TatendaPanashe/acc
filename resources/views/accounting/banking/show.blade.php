@extends('layouts.main')

@section('title', 'Reconcile Statement')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Reconcile Statement', 'section' => 'Banking'])
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><strong>{{ $statement->bankAccount->label() }}</strong> statement {{ $statement->statement_date->format('d M Y') }}</h2>
            <div class="d-flex gap-2">
                @unless($statement->isReconciled())
                    @canroute('accounting.banking.statements.auto-match', 'POST')
                        <form method="POST" action="{{ route('accounting.banking.statements.auto-match', $statement) }}">@csrf<button class="btn btn-outline-primary btn-sm">Auto-match</button></form>
                    @endcanroute
                    @canroute('accounting.banking.statements.complete', 'POST')
                        <form method="POST" action="{{ route('accounting.banking.statements.complete', $statement) }}">@csrf<button class="btn btn-success btn-sm">Complete reconciliation</button></form>
                    @endcanroute
                @else
                    <span class="badge bg-success">Reconciled {{ $statement->reconciled_at?->format('d M Y H:i') }}</span>
                @endunless
            </div>
        </div>
        <div class="body">
            <div class="row text-center">
                <div class="col-md-2"><small class="text-muted d-block">Opening</small><strong>{{ number_format((float) $statement->opening_balance, 2) }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Transactions</small><strong>{{ number_format($summary['statement_movement'], 2) }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Closing</small><strong>{{ number_format((float) $statement->closing_balance, 2) }}</strong>
                    @if($summary['statement_check'] != 0)<br><span class="badge bg-danger">Doesn't add up ({{ number_format($summary['statement_check'], 2) }})</span>@endif</div>
                <div class="col-md-2"><small class="text-muted d-block">GL balance</small><strong>{{ number_format($summary['gl_balance'], 2) }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Not yet on statement</small><strong>{{ number_format($summary['unpresented_total'], 2) }}</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Difference</small>
                    <strong class="{{ $summary['difference'] == 0 ? 'text-success' : 'text-danger' }}">{{ number_format($summary['difference'], 2) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="header"><h2><strong>Statement</strong> lines</h2></div>
        <div class="body table-responsive">
            <table class="table">
                <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th class="text-end">Amount</th><th>Matched to</th><th></th></tr></thead>
                <tbody>
                @foreach($statement->lines as $line)
                    <tr class="{{ $line->journal_line_id ? '' : 'table-warning' }}">
                        <td>{{ $line->transaction_date->format('Y-m-d') }}</td>
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->reference }}</td>
                        <td class="text-end">{{ number_format((float) $line->amount, 2) }}</td>
                        <td>
                            @if($line->journalLine)
                                {{ $line->journalLine->journalEntry?->entry_number }} ({{ $line->journalLine->journalEntry?->entry_date?->format('Y-m-d') }})
                            @elseif(!$statement->isReconciled())
                                @php($options = $candidates[$line->id] ?? collect())
                                @if($options->isNotEmpty())
                                    @canroute('accounting.banking.lines.match', 'POST')
                                        <form method="POST" action="{{ route('accounting.banking.lines.match', $line) }}" class="d-flex gap-1">
                                            @csrf
                                            <select name="journal_line_id" class="form-control form-control-sm">
                                                @foreach($options as $gl)<option value="{{ $gl->id }}">{{ $gl->journalEntry->entry_number }} · {{ $gl->journalEntry->entry_date->format('Y-m-d') }} · {{ $gl->description }}</option>@endforeach
                                            </select>
                                            <button class="btn btn-sm btn-primary">Match</button>
                                        </form>
                                    @endcanroute
                                @else
                                    <span class="text-muted">No matching GL entry</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-end">
                            @if($line->journal_line_id && !$statement->isReconciled())
                                @canroute('accounting.banking.lines.unmatch', 'DELETE')
                                    <form method="POST" action="{{ route('accounting.banking.lines.unmatch', $line) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary">Unmatch</button></form>
                                @endcanroute
                            @elseif(!$line->journal_line_id && !$statement->isReconciled())
                                @canroute('accounting.banking.lines.journal', 'POST')
                                    <form method="POST" action="{{ route('accounting.banking.lines.journal', $line) }}" class="d-flex gap-1" onsubmit="return confirm('Post a journal for this bank line?')">
                                        @csrf
                                        <select name="account_id" class="form-control form-control-sm" required>
                                            <option value="">Post to…</option>
                                            @foreach($counterAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-success">Post</button>
                                    </form>
                                @endcanroute
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($summary['unpresented']->isNotEmpty())
        <div class="card">
            <div class="header"><h2><strong>In the books</strong>, not yet on the statement</h2></div>
            <div class="body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Entry</th><th>Description</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    @foreach($summary['unpresented'] as $gl)
                        <tr><td>{{ $gl->journalEntry->entry_date->format('Y-m-d') }}</td><td>{{ $gl->journalEntry->entry_number }}</td><td>{{ $gl->description }}</td><td class="text-end">{{ number_format($reconciliation->glAmount($gl, $statement), 2) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

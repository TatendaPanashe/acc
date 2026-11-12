@extends('layouts.main')

@section('title', 'Journal Details')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Journal Details', 'section' => 'General Ledger'])

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><strong>{{ $journal->entry_number }}</strong> {{ ucfirst(str_replace('_', ' ', $journal->status)) }}</h2>
            <div class="d-flex gap-2">
                @if($journal->status !== 'posted')
                    <a href="{{ route('accounting.gl.journals.edit', $journal) }}" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i> Edit</a>
                    @canroute('accounting.gl.journals.post', 'POST')<form method="POST" action="{{ route('accounting.gl.journals.post', $journal) }}">
                        @csrf
                        <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i> Post</button>
                    </form>@else<span class="badge bg-warning text-dark">Awaiting approval</span>@endcanroute
                @endif
            </div>
        </div>
        <div class="body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Date:</strong> {{ $journal->entry_date?->format('Y-m-d') }}</div>
                <div class="col-md-3"><strong>Currency:</strong> {{ $journal->currency }}</div>
                <div class="col-md-3"><strong>Posted:</strong> {{ $journal->posted_at?->format('Y-m-d H:i') ?? 'No' }}</div>
                <div class="col-md-3"><strong>Source:</strong> {{ ucwords(str_replace('_', ' ', $journal->source_module)) }}</div>
            </div>
            @if($journal->memo)
                <p class="text-muted">{{ $journal->memo }}</p>
            @endif
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Account</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                    <tbody>
                        @foreach($journal->lines as $line)
                            <tr>
                                <td>{{ $line->account?->code }} - {{ $line->account?->name }}</td>
                                <td>{{ $line->description }}</td>
                                <td class="text-end">{{ number_format($line->debit, 2) }}</td>
                                <td class="text-end">{{ number_format($line->credit, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-end">Totals</th>
                            <th class="text-end">{{ number_format($journal->lines->sum('debit'), 2) }}</th>
                            <th class="text-end">{{ number_format($journal->lines->sum('credit'), 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', 'Journal Entries')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Journal Entries', 'section' => 'General Ledger'])

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><strong>Journal</strong> Register</h2>
            <a href="{{ route('accounting.gl.journals.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Journal</a>
        </div>
        <div class="body table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($journals as $journal)
                        <tr>
                            <td>{{ $journal->entry_number }}</td>
                            <td>{{ $journal->entry_date?->format('Y-m-d') }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $journal->source_module)) }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $journal->status)) }}</td>
                            <td class="text-end">{{ number_format($journal->lines->sum('debit'), 2) }}</td>
                            <td class="text-end">{{ number_format($journal->lines->sum('credit'), 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('accounting.gl.journals.show', $journal) }}" class="btn btn-light btn-sm"><i class="fas fa-eye"></i></a>
                                @if($journal->status !== 'posted')
                                    <a href="{{ route('accounting.gl.journals.edit', $journal) }}" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i></a>
                                    @canroute('accounting.gl.journals.post', 'POST')<form method="POST" action="{{ route('accounting.gl.journals.post', $journal) }}">
                                        @csrf
                                        <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i> Post</button>
                                    </form>@else<span class="badge bg-warning text-dark">Awaiting approval</span>@endcanroute
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No journal entries have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $journals->links() }}
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', 'Trial Balance')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Trial Balance', 'section' => 'Financial Reports'])
    @include('accounting.gl.reports._period_filter', ['route' => route('accounting.gl.trial-balance')])
    <div class="card">
        <div class="header"><h2><strong>Trial</strong> Balance as at {{ ($period?->ends_on ?? now())->format('d M Y') }}</h2></div>
        <div class="body table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Account</th><th>Type</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row->code }} - {{ $row->name }}</td>
                            <td>{{ ucfirst($row->type) }}</td>
                            <td class="text-end">{{ number_format($row->debit_total, 2) }}</td>
                            <td class="text-end">{{ number_format($row->credit_total, 2) }}</td>
                            <td class="text-end">{{ number_format($row->balance, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th colspan="2" class="text-end">Totals</th><th class="text-end">{{ number_format($totals['debit'], 2) }}</th><th class="text-end">{{ number_format($totals['credit'], 2) }}</th><th></th></tr></tfoot>
            </table>
        </div>
    </div>
@endsection

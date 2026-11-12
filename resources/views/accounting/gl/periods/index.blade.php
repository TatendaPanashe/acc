@extends('layouts.main')

@section('title', 'Accounting Periods')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Accounting Periods', 'section' => 'General Ledger'])
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="header"><h2><strong>Accounting</strong> Periods</h2></div>
                <div class="body table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>Name</th><th>Fiscal Year</th><th>Start</th><th>End</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse($periods as $period)
                                <tr>
                                    <td>{{ $period->name }}</td>
                                    <td>{{ $period->fiscalYear?->name }}</td>
                                    <td>{{ $period->starts_on?->format('Y-m-d') }}</td>
                                    <td>{{ $period->ends_on?->format('Y-m-d') }}</td>
                                    <td>{{ ucfirst($period->status) }}</td>
                                    <td class="text-end"><a href="{{ route('accounting.gl.periods.edit', $period) }}" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No periods have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $periods->links() }}
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="header"><h2><strong>New</strong> Period</h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('accounting.gl.periods.store') }}">
                        @include('accounting.gl.periods._form', ['period' => null])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

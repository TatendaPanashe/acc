@extends('layouts.main')

@section('title', 'Fiscal Years')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Fiscal Years', 'section' => 'General Ledger'])

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="header"><h2><strong>Fiscal</strong> Years</h2></div>
                <div class="body table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Status</th><th>Periods</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse($fiscalYears as $year)
                                <tr>
                                    <td>{{ $year->name }}</td>
                                    <td>{{ $year->starts_on?->format('Y-m-d') }}</td>
                                    <td>{{ $year->ends_on?->format('Y-m-d') }}</td>
                                    <td>{{ ucfirst($year->status) }}</td>
                                    <td>{{ $year->periods_count }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('accounting.gl.fiscal-years.edit', $year) }}" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i></a>
                                        <form method="POST" action="{{ route('accounting.gl.fiscal-years.destroy', $year) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-warning btn-sm"><i class="fas fa-lock"></i></button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No fiscal years have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $fiscalYears->links() }}
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="header"><h2><strong>New</strong> Fiscal Year</h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('accounting.gl.fiscal-years.store') }}">
                        @include('accounting.gl.fiscal_years._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

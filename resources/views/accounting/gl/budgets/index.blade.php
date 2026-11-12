@extends('layouts.main')

@section('title', 'Budgets')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Budgets', 'section' => 'General Ledger'])
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="header"><h2><strong>Budget</strong> Register</h2></div>
                <div class="body table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>Period</th><th>Account</th><th>Currency</th><th class="text-end">Amount</th><th>Notes</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse($budgets as $budget)
                                <tr>
                                    <td>{{ $budget->period?->name }}</td>
                                    <td>{{ $budget->account?->code }} - {{ $budget->account?->name }}</td>
                                    <td>{{ $budget->currency }}</td>
                                    <td class="text-end">{{ number_format($budget->amount, 2) }}</td>
                                    <td>{{ $budget->notes }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('accounting.gl.budgets.edit', $budget) }}" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i></a>
                                        <form method="POST" action="{{ route('accounting.gl.budgets.destroy', $budget) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No budget values have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $budgets->links() }}
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="header"><h2><strong>Set</strong> Budget</h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('accounting.gl.budgets.store') }}">
                        @include('accounting.gl.budgets._form', ['budget' => null])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

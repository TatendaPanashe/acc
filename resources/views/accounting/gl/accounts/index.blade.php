@extends('layouts.main')

@section('title', 'Chart of Accounts')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Chart of Accounts', 'section' => 'General Ledger'])

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="header"><h2><strong>Accounts</strong></h2></div>
                <div class="body table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Normal</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $account)
                                <tr>
                                    <td>{{ $account->code }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td>{{ ucfirst($account->type) }}</td>
                                    <td>{{ ucfirst($account->normal_balance) }}</td>
                                    <td>{{ $account->status ? 'Active' : 'Inactive' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('accounting.gl.accounts.edit', $account) }}" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i></a>
                                        <form method="POST" action="{{ route('accounting.gl.accounts.destroy', $account) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No accounts have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $accounts->links() }}
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="header"><h2><strong>New</strong> Account</h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('accounting.gl.accounts.store') }}">
                        @include('accounting.gl.accounts._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

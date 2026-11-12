@extends('layouts.main')

@section('title', 'Edit Account')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Edit Account', 'section' => 'General Ledger'])

    <div class="card">
        <div class="header"><h2><strong>{{ $account->code }}</strong> {{ $account->name }}</h2></div>
        <div class="body">
            <form method="POST" action="{{ route('accounting.gl.accounts.update', $account) }}">
                @include('accounting.gl.accounts._form', ['account' => $account])
                <a href="{{ route('accounting.gl.accounts.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

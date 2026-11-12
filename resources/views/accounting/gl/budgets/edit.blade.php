@extends('layouts.main')

@section('title', 'Edit Budget')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Edit Budget', 'section' => 'General Ledger'])
    <div class="card">
        <div class="header"><h2><strong>Edit</strong> Budget</h2></div>
        <div class="body">
            <form method="POST" action="{{ route('accounting.gl.budgets.update', $budget) }}">
                @include('accounting.gl.budgets._form')
                <a href="{{ route('accounting.gl.budgets.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

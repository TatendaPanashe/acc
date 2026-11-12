@extends('layouts.main')

@section('title', 'New Journal')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'New Journal', 'section' => 'General Ledger'])

    <div class="card">
        <div class="header"><h2><strong>Create</strong> Journal Entry</h2></div>
        <div class="body">
            <form method="POST" action="{{ route('accounting.gl.journals.store') }}">
                @include('accounting.gl.journals._form')
            </form>
        </div>
    </div>
@endsection

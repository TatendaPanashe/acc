@extends('layouts.main')

@section('title', 'Edit Journal')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Edit Journal', 'section' => 'General Ledger'])

    <div class="card">
        <div class="header"><h2><strong>{{ $journal->entry_number }}</strong></h2></div>
        <div class="body">
            <form method="POST" action="{{ route('accounting.gl.journals.update', $journal) }}">
                @include('accounting.gl.journals._form')
            </form>
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', 'Edit Period')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Edit Period', 'section' => 'General Ledger'])
    <div class="card">
        <div class="header"><h2><strong>{{ $period->name }}</strong></h2></div>
        <div class="body">
            <form method="POST" action="{{ route('accounting.gl.periods.update', $period) }}">
                @include('accounting.gl.periods._form')
                <a href="{{ route('accounting.gl.periods.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

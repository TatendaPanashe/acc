@extends('layouts.main')

@section('title', 'Edit Fiscal Year')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Edit Fiscal Year', 'section' => 'General Ledger'])
    <div class="card">
        <div class="header"><h2><strong>{{ $fiscalYear->name }}</strong></h2></div>
        <div class="body">
            <form method="POST" action="{{ route('accounting.gl.fiscal-years.update', $fiscalYear) }}">
                @include('accounting.gl.fiscal_years._form')
                <a href="{{ route('accounting.gl.fiscal-years.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection

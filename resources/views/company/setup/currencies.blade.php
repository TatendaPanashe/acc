@extends('layouts.main')

@section('title', 'Setup: Currencies')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Currencies', 'section' => 'Company Setup'])
    @include('company.setup._steps')

    <form method="POST" action="{{ route('company.setup.currencies.store') }}">
        @csrf
        <div class="card">
            <div class="header"><h2><strong>Step 2</strong> Currencies</h2></div>
            <div class="body">
                <p>The <strong>base currency</strong> is the currency your books and financial statements are kept in.
                   Add any other currencies you invoice, buy or bank in.</p>
                <table class="table">
                    <thead><tr><th>Currency</th><th class="text-center">Base</th><th class="text-center">In use</th></tr></thead>
                    <tbody>
                    @foreach($options as $code => [$name, $symbol])
                        <tr>
                            <td><strong>{{ $code }}</strong> {{ $name }} <span class="text-muted">({{ $symbol }})</span></td>
                            <td class="text-center"><input type="radio" name="base_currency" value="{{ $code }}" @checked(old('base_currency', $base) === $code) required></td>
                            <td class="text-center"><input type="checkbox" name="currencies[]" value="{{ $code }}" @checked(in_array($code, old('currencies', $selected), true))></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="text-muted">More currencies and exchange rates can be added later in Accounting Settings.</p>
                <a href="{{ route('company.setup.edit') }}" class="btn btn-secondary">Back</a>
                <button class="btn btn-success">Save &amp; continue</button>
            </div>
        </div>
    </form>
@endsection

@extends('layouts.main')

@section('title', 'Setup: Fiscal Year & Accounts')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Fiscal Year & Accounts', 'section' => 'Company Setup'])
    @include('company.setup._steps')

    <form method="POST" action="{{ route('company.setup.ledger.store') }}">
        @csrf
        <div class="row">
            <div class="col-xl-5">
                <div class="card">
                    <div class="header"><h2><strong>Step 4</strong> Fiscal year</h2></div>
                    <div class="body">
                        @if($fiscalYear)
                            <p>Fiscal year <strong>{{ $fiscalYear->name }}</strong> ({{ $fiscalYear->starts_on->format('d M Y') }} to {{ $fiscalYear->ends_on->format('d M Y') }}) already exists.</p>
                            <input type="hidden" name="start_month" value="{{ $fiscalYear->starts_on->month }}">
                            <input type="hidden" name="start_year" value="{{ $fiscalYear->starts_on->year }}">
                        @else
                            <p>Twelve monthly accounting periods will be created.</p>
                            <div class="row">
                                <div class="col-7 mb-3">
                                    <label class="form-label">First month</label>
                                    <select name="start_month" class="form-control">
                                        @foreach(range(1, 12) as $m)
                                            <option value="{{ $m }}" @selected((int) old('start_month', 1) === $m)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-5 mb-3">
                                    <label class="form-label">Year</label>
                                    <input type="number" name="start_year" class="form-control" value="{{ old('start_year', now()->year) }}" min="2000" max="2100" required>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card">
                    <div class="header"><h2><strong>Standard</strong> chart of accounts</h2></div>
                    <div class="body table-responsive" style="max-height: 420px; overflow-y: auto;">
                        <p>These accounts will be created. Accounts you already have are kept as they are. You can add or rename accounts later.</p>
                        <table class="table table-sm">
                            <thead><tr><th>Code</th><th>Name</th><th>Type</th><th></th></tr></thead>
                            <tbody>
                            @foreach($accounts as [$code, $name, $type])
                                <tr>
                                    <td>{{ $code }}</td><td>{{ $name }}</td><td>{{ ucfirst($type) }}</td>
                                    <td>@if(in_array($code, $existingCodes, true))<span class="badge bg-secondary">exists</span>@endif</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <a href="{{ route('company.setup.tax') }}" class="btn btn-secondary">Back</a>
        <button class="btn btn-success">Create &amp; finish setup</button>
    </form>
@endsection

@extends('layouts.main')

@section('title', 'Setup: Tax & Statutory')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Tax & Statutory', 'section' => 'Company Setup'])
    @include('company.setup._steps')

    <form method="POST" action="{{ route('company.setup.tax.store') }}">
        @csrf
        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="header"><h2><strong>Step 3</strong> Statutory registrations</h2></div>
                    <div class="body">
                        <div class="mb-3">
                            <label class="form-label">VAT Number</label>
                            <input class="form-control" value="{{ $company->vat_number }}" disabled>
                            <small class="text-muted">From your company profile.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ZIMRA Taxpayer Identification Number (TIN)</label>
                            <input name="tin" class="form-control" value="{{ old('tin', $company->tin) }}" maxlength="30" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">NSSA Employer Number <span class="text-muted">(optional)</span></label>
                            <input name="nssa_number" class="form-control" value="{{ old('nssa_number', $company->nssa_number) }}" maxlength="30">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="header"><h2><strong>VAT</strong> codes</h2></div>
                    <div class="body">
                        <div class="mb-3">
                            <label class="form-label">Standard VAT rate (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="standard_rate" class="form-control" value="{{ old('standard_rate', $standardRate) }}" required>
                            <small class="text-muted">Check the current ZIMRA rate before continuing.</small>
                        </div>
                        <p class="mb-2">These tax codes will be created:</p>
                        <ul>
                            <li><strong>VAT-STD</strong> standard-rated</li>
                            <li><strong>VAT-ZERO</strong> zero-rated (0%)</li>
                        </ul>
                        <input type="hidden" name="include_exempt" value="0">
                        <label><input type="checkbox" name="include_exempt" value="1" @checked(old('include_exempt', true))> Also create <strong>VAT-EXEMPT</strong></label>
                    </div>
                </div>
            </div>
        </div>
        <a href="{{ route('company.setup.currencies') }}" class="btn btn-secondary">Back</a>
        <button class="btn btn-success">Save &amp; continue</button>
    </form>
@endsection

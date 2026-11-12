@extends('layouts.main')

@section('title', 'Company Setup')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Company Profile', 'section' => 'Company Setup'])
    @include('company.setup._steps')

    <form method="POST" action="{{ route('company.setup.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xl-5">
                <div class="card">
                    <div class="header"><h2><strong>Company Details</strong></h2></div>
                    <div class="body">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input name="name" class="form-control" value="{{ old('name', $company->name ?? '') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">VAT Number</label>
                            <input name="vat_number" class="form-control" value="{{ old('vat_number', $company->vat_number ?? '') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Registration Number</label>
                            <input name="registration_number" class="form-control" value="{{ old('registration_number', $company->registration_number ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $company->email ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input name="phone" class="form-control" value="{{ old('phone', $company->phone ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <select name="country" class="form-control" required>
                                <option value="">Select country</option>
                                @foreach(config('platform.countries') as $code => $countryName)
                                    <option value="{{ $code }}" @selected(old('country', $company->country ?? 'ZW') === $code)>{{ $countryName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="4">{{ old('address', $company->address ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card">
                    <div class="header d-flex justify-content-between align-items-center">
                        <h2><strong>Bank Accounts</strong></h2>
                        <button type="button" class="btn btn-sm btn-primary" id="add-bank-account">
                            <i data-feather="plus"></i> Add
                        </button>
                    </div>
                    <div class="body">
                        @php
                            $rows = old('bank_accounts');
                            if (!$rows) {
                                $rows = $bankAccounts->isNotEmpty()
                                    ? $bankAccounts->map(fn($account) => $account->only(['bank_name', 'account_name', 'account_number', 'branch_code', 'swift_code', 'currency']))->toArray()
                                    : [['bank_name' => '', 'account_name' => '', 'account_number' => '', 'branch_code' => '', 'swift_code' => '', 'currency' => 'USD']];
                            }
                            $primary = old('primary_bank_account', max(0, $bankAccounts->search(fn($account) => $account->is_primary)));
                        @endphp

                        <div id="bank-accounts">
                            @foreach($rows as $index => $account)
                                <div class="bank-account border rounded p-3 mb-3">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Bank Name</label>
                                            <input data-name="bank_name" name="bank_accounts[{{ $index }}][bank_name]" class="form-control" value="{{ $account['bank_name'] ?? '' }}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Account Name</label>
                                            <input data-name="account_name" name="bank_accounts[{{ $index }}][account_name]" class="form-control" value="{{ $account['account_name'] ?? '' }}" required>
                                        </div>
                                        <div class="col-md-5 mb-3">
                                            <label class="form-label">Account Number</label>
                                            <input data-name="account_number" name="bank_accounts[{{ $index }}][account_number]" class="form-control" value="{{ $account['account_number'] ?? '' }}" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Branch Code</label>
                                            <input data-name="branch_code" name="bank_accounts[{{ $index }}][branch_code]" class="form-control" value="{{ $account['branch_code'] ?? '' }}">
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">SWIFT</label>
                                            <input data-name="swift_code" name="bank_accounts[{{ $index }}][swift_code]" class="form-control" value="{{ $account['swift_code'] ?? '' }}">
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Currency</label>
                                            <input data-name="currency" name="bank_accounts[{{ $index }}][currency]" class="form-control text-uppercase" maxlength="3" value="{{ $account['currency'] ?? 'USD' }}" required>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="mb-0">
                                                <input type="radio" name="primary_bank_account" value="{{ $index }}" @checked((int) $primary === $index)>
                                                Primary bank account
                                            </label>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-bank-account">
                                                <i data-feather="trash-2"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button class="btn btn-success" type="submit">{{ $company->onboarding_completed_at ? 'Save profile' : 'Save & continue' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <template id="bank-account-template">
        <div class="bank-account border rounded p-3 mb-3">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Bank Name</label><input data-name="bank_name" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Account Name</label><input data-name="account_name" class="form-control" required></div>
                <div class="col-md-5 mb-3"><label class="form-label">Account Number</label><input data-name="account_number" class="form-control" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">Branch Code</label><input data-name="branch_code" class="form-control"></div>
                <div class="col-md-2 mb-3"><label class="form-label">SWIFT</label><input data-name="swift_code" class="form-control"></div>
                <div class="col-md-2 mb-3"><label class="form-label">Currency</label><input data-name="currency" class="form-control text-uppercase" maxlength="3" value="USD" required></div>
                <div class="col-md-8"><label class="mb-0"><input type="radio" name="primary_bank_account"> Primary bank account</label></div>
                <div class="col-md-4 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-bank-account"><i data-feather="trash-2"></i> Remove</button></div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('bank-accounts');
            const template = document.getElementById('bank-account-template');

            function refreshRows() {
                container.querySelectorAll('.bank-account').forEach((row, index) => {
                    row.querySelectorAll('[data-name]').forEach((input) => {
                        input.name = `bank_accounts[${index}][${input.dataset.name}]`;
                    });
                    row.querySelector('input[type="radio"]').value = index;
                });
            }

            document.getElementById('add-bank-account').addEventListener('click', function () {
                container.appendChild(template.content.cloneNode(true));
                refreshRows();
                if (window.feather) window.feather.replace();
            });

            container.addEventListener('click', function (event) {
                if (!event.target.closest('.remove-bank-account')) return;
                if (container.querySelectorAll('.bank-account').length === 1) return;
                event.target.closest('.bank-account').remove();
                refreshRows();
            });

            refreshRows();
        });
    </script>
@endsection

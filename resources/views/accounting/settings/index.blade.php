@extends('layouts.main')

@section('title', 'Accounting Settings')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Accounting Settings', 'section' => 'Configuration'])

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="header"><h2><strong>Currencies</strong></h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('accounting.settings.currencies.store') }}" class="row">
                        @csrf
                        <div class="col-md-3 mb-3"><input name="code" class="form-control" placeholder="Code" maxlength="3" required></div>
                        <div class="col-md-4 mb-3"><input name="name" class="form-control" placeholder="Name" required></div>
                        <div class="col-md-2 mb-3"><input name="symbol" class="form-control" placeholder="$"></div>
                        <div class="col-md-3 mb-3"><button class="btn btn-primary w-100" type="submit"><i data-feather="plus"></i> Add</button></div>
                        <div class="col-md-6 mb-3"><label><input type="checkbox" name="is_base" value="1"> Base currency</label></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Code</th><th>Name</th><th>Symbol</th><th>Base</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                                @forelse($currencies as $currency)
                                    <tr>
                                        <td>{{ $currency->code }}</td>
                                        <td>{{ $currency->name }}</td>
                                        <td>{{ $currency->symbol }}</td>
                                        <td>{{ $currency->is_base ? 'Yes' : 'No' }}</td>
                                        <td>{{ $currency->status ? 'Active' : 'Inactive' }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('accounting.settings.currencies.destroy', $currency) }}" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit"><i data-feather="trash-2"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted">No currencies yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $currencies->links() }}
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="header"><h2><strong>Tax Codes</strong></h2></div>
                <div class="body">
                    <form method="POST" action="{{ route('accounting.settings.tax-codes.store') }}" class="row">
                        @csrf
                        <div class="col-md-3 mb-3"><input name="code" class="form-control" placeholder="Code" required></div>
                        <div class="col-md-5 mb-3"><input name="name" class="form-control" placeholder="Name" required></div>
                        <div class="col-md-4 mb-3"><input name="rate" type="number" step="0.0001" class="form-control" placeholder="Rate" required></div>
                        <div class="col-md-4 mb-3">
                            <select name="type" class="form-control">
                                <option value="both">Both</option>
                                <option value="sales">Sales</option>
                                <option value="purchase">Purchase</option>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <select name="control_account_id" class="form-control">
                                @foreach($accounts as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3"><button class="btn btn-primary w-100" type="submit"><i data-feather="plus"></i> Add</button></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Rate</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                                @forelse($taxCodes as $taxCode)
                                    <tr>
                                        <td>{{ $taxCode->code }}</td>
                                        <td>{{ $taxCode->name }}</td>
                                        <td>{{ ucfirst($taxCode->type) }}</td>
                                        <td>{{ number_format((float) $taxCode->rate, 4) }}%</td>
                                        <td>{{ $taxCode->status ? 'Active' : 'Inactive' }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('accounting.settings.tax-codes.destroy', $taxCode) }}" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit"><i data-feather="trash-2"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted">No tax codes yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $taxCodes->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-5">
            <div class="card">
                <div class="header"><h2><strong>Exchange</strong> Rates</h2></div>
                <div class="body">
                    <p class="text-muted">Base currency: <strong>{{ $baseCurrency }}</strong>. Documents in other currencies use the latest rate on or before their date.</p>
                    @canroute('accounting.settings.rates.store', 'POST')
                        @if($foreignCurrencies->isEmpty())
                            <p class="text-muted">Add a non-base currency above to record rates.</p>
                        @else
                            <form method="POST" action="{{ route('accounting.settings.rates.store') }}" class="row g-2 mb-3">
                                @csrf
                                <div class="col-4"><label class="form-label">1 unit of</label><select name="from_currency" class="form-control">@foreach($foreignCurrencies as $code)<option>{{ $code }}</option>@endforeach</select></div>
                                <div class="col-4"><label class="form-label">= {{ $baseCurrency }}</label><input name="rate" type="number" step="0.00000001" min="0" class="form-control" required></div>
                                <div class="col-4"><label class="form-label">From date</label><input name="rate_date" type="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                                <div class="col-8"><input name="source" class="form-control" placeholder="Source, e.g. RBZ interbank"></div>
                                <div class="col-4"><button class="btn btn-primary w-100">Save rate</button></div>
                            </form>
                        @endif
                    @endcanroute
                    <table class="table table-sm">
                        <thead><tr><th>Date</th><th>Currency</th><th class="text-end">Rate</th><th>Source</th><th></th></tr></thead>
                        <tbody>
                        @forelse($exchangeRates as $rate)
                            <tr>
                                <td>{{ $rate->rate_date->format('Y-m-d') }}</td>
                                <td>1 {{ $rate->from_currency }} = </td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $rate->rate, 8), '0'), '.') }} {{ $rate->to_currency }}</td>
                                <td class="text-muted">{{ $rate->source }}</td>
                                <td class="text-end">
                                    @canroute('accounting.settings.rates.destroy', 'DELETE')
                                        <form method="POST" action="{{ route('accounting.settings.rates.destroy', $rate) }}" onsubmit="return confirm('Delete this rate?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                    @endcanroute
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No exchange rates yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    {{ $exchangeRates->links() }}
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card">
                <div class="header"><h2><strong>Account</strong> Mapping</h2></div>
                <div class="body">
                    <p class="text-muted">The GL accounts that automatic postings use. Leave blank to use the default account code.</p>
                    <form method="POST" action="{{ route('accounting.settings.mappings.update') }}">
                        @csrf @method('PUT')
                        <table class="table table-sm">
                            @foreach($mappingGroups as $group => $items)
                                <tr><th colspan="2" class="pt-3">{{ $group }}</th></tr>
                                @foreach($items as $key => [$label, $defaultCode])
                                    <tr>
                                        <td class="align-middle">{{ $label }}</td>
                                        <td>
                                            <select name="mappings[{{ $key }}]" class="form-control form-control-sm">
                                                <option value="">Default ({{ $defaultCode }})</option>
                                                @foreach($postableAccounts as $account)
                                                    <option value="{{ $account->id }}" @selected(($mapped[$key] ?? null) == $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </table>
                        @canroute('accounting.settings.mappings.update', 'PUT')
                            <button class="btn btn-success">Save mapping</button>
                        @endcanroute
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.main')

@section('title', 'Statutory settings')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Statutory settings', 'section' => 'Payroll'])
    @include('payroll._tabs')

    @php
        // Form values: previous input, then published figures (when pre-filling), then the saved settings.
        $val = function (string $key) use ($prefill, $setting) {
            if ($prefill) return $prefill[$key] ?? null;
            $v = $setting?->$key;
            return is_numeric($v) ? (float) $v : $v;
        };
        $bands = old('bands', $prefill['bands'] ?? ($setting?->bands->map(fn ($b) => ['lower' => (float) $b->lower, 'upper' => $b->upper !== null ? (float) $b->upper : null, 'rate' => (float) $b->rate, 'deduct' => (float) $b->deduct])->all() ?: [['lower' => 0, 'upper' => null, 'rate' => 0, 'deduct' => 0]]));
        $credits = old('credits', $prefill['credits'] ?? ($setting?->credits ?: []));
        $levies = old('levies', $prefill['levies'] ?? ($setting?->levies ?: []));
        $canEdit = app(\App\Services\Authorization\RoutePermissions::class)->allows(auth()->user(), 'accounting.payroll.settings.store', 'POST');
    @endphp

    <div class="card">
        <div class="body d-flex flex-wrap gap-3 justify-content-between align-items-end">
            <form method="GET" class="d-flex gap-2 align-items-end">
                <div><label class="form-label">Tax year</label><input type="number" name="year" class="form-control" value="{{ $year }}" min="2000" max="2100" style="width:110px"></div>
                <div><label class="form-label">Currency</label><select name="currency" class="form-control">@foreach($currencies as $c)<option @selected($currency === $c)>{{ $c }}</option>@endforeach</select></div>
                <button class="btn btn-outline-primary">Open</button>
            </form>
            <div class="small">
                @forelse($existing as $s)
                    <a href="{{ route('accounting.payroll.settings.index', ['year' => $s->tax_year, 'currency' => $s->currency]) }}" class="badge {{ $s->tax_year == $year && $s->currency === $currency ? 'bg-primary' : 'bg-light text-dark' }} me-1">{{ $s->tax_year }} {{ $s->currency }} · {{ $s->bands_count }} bands</a>
                @empty
                    <span class="text-muted">No statutory settings entered yet.</span>
                @endforelse
            </div>
        </div>
    </div>

    @if($prefill)
        <div class="alert alert-warning small">
            <strong>Pre-filled with published figures, not yet saved:</strong> {{ $prefill['label'] }} ({{ $prefill['source'] }}).
            @if($prefill['set_year'] !== $year) ZIMRA had not published separate {{ $year }} tables when these were loaded, so these are the {{ $prefill['set_year'] }} tables. Check zimra.co.zw for newer tables. @endif
            Review the figures and save to use them. NSSA "reduces taxable income" is widely applied but should be confirmed with your tax adviser.
        </div>
    @else
        <div class="alert alert-info small d-flex justify-content-between flex-wrap gap-2">
            <span>Figures for <strong>{{ $year }}</strong> in <strong>{{ $currency }}</strong>, as entered by your Company Admin. Employees paid in both USD and ZWG are taxed on the USD table on their total pay, with the tax apportioned between the currencies (ZIMRA rule).</span>
            @if($published && $canEdit)<a href="{{ route('accounting.payroll.settings.index', ['year' => $year, 'currency' => $currency, 'published' => 1]) }}" class="alert-link">Load published ZIMRA/NSSA figures</a>@endif
        </div>
    @endif

    <form method="POST" action="{{ route('accounting.payroll.settings.store') }}">
        @csrf
        <input type="hidden" name="tax_year" value="{{ $year }}"><input type="hidden" name="currency" value="{{ $currency }}">
        <fieldset @disabled(!$canEdit)>
        <div class="row">
            <div class="col-xl-7">
                <div class="card">
                    <div class="header"><h2><strong>PAYE</strong> table (monthly)</h2><small class="text-muted">ZIMRA format: tax = taxable income × rate − deduct, using the band the income falls in</small></div>
                    <div class="body table-responsive">
                        <table class="table table-sm align-middle" id="bands">
                            <thead><tr><th>From</th><th>To</th><th>Rate %</th><th>Deduct</th><th></th></tr></thead>
                            <tbody>
                            @foreach($bands as $i => $band)
                                <tr>
                                    <td><input type="number" step="0.01" min="0" name="bands[{{ $i }}][lower]" class="form-control form-control-sm" value="{{ $band['lower'] }}" required></td>
                                    <td><input type="number" step="0.01" min="0" name="bands[{{ $i }}][upper]" class="form-control form-control-sm" value="{{ $band['upper'] }}" placeholder="and above"></td>
                                    <td><input type="number" step="0.001" min="0" max="100" name="bands[{{ $i }}][rate]" class="form-control form-control-sm" value="{{ $band['rate'] }}" required></td>
                                    <td><input type="number" step="0.01" min="0" name="bands[{{ $i }}][deduct]" class="form-control form-control-sm" value="{{ $band['deduct'] }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-link text-danger remove-row" aria-label="Remove band">✕</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-primary add-row" data-table="bands">Add band</button>
                    </div>
                </div>
                <div class="card">
                    <div class="header"><h2><strong>Tax credits</strong></h2><small class="text-muted">fixed monthly amounts off PAYE (e.g. elderly or disabled persons); tick them on each employee</small></div>
                    <div class="body table-responsive">
                        <table class="table table-sm align-middle" id="credits">
                            <thead><tr><th>Code</th><th>Name</th><th>Monthly amount</th><th>Currency</th><th></th></tr></thead>
                            <tbody>
                            @foreach($credits ?: [['code' => '', 'name' => '', 'amount' => '']] as $i => $credit)
                                <tr>
                                    <td><input name="credits[{{ $i }}][code]" class="form-control form-control-sm" value="{{ $credit['code'] ?? '' }}" placeholder="elderly"></td>
                                    <td><input name="credits[{{ $i }}][name]" class="form-control form-control-sm" value="{{ $credit['name'] ?? '' }}" placeholder="Elderly person credit"></td>
                                    <td><input type="number" step="0.01" min="0" name="credits[{{ $i }}][amount]" class="form-control form-control-sm" value="{{ $credit['amount'] ?? '' }}"></td>
                                    <td><select name="credits[{{ $i }}][currency]" class="form-control form-control-sm">@foreach($currencies as $c)<option @selected(($credit['currency'] ?? $currency) === $c)>{{ $c }}</option>@endforeach</select></td>
                                    <td><button type="button" class="btn btn-sm btn-link text-danger remove-row" aria-label="Remove credit">✕</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-primary add-row" data-table="credits">Add credit</button>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card">
                    <div class="header"><h2><strong>NSSA</strong> &amp; AIDS levy</h2></div>
                    <div class="body row g-2">
                        <div class="col-6"><label class="form-label">NSSA employee %</label><input type="number" step="0.001" min="0" max="100" name="nssa_employee_rate" class="form-control" required value="{{ old('nssa_employee_rate', $val('nssa_employee_rate')) }}"></div>
                        <div class="col-6"><label class="form-label">NSSA employer %</label><input type="number" step="0.001" min="0" max="100" name="nssa_employer_rate" class="form-control" required value="{{ old('nssa_employer_rate', $val('nssa_employer_rate')) }}"></div>
                        <div class="col-8"><label class="form-label">Monthly insurable earnings ceiling</label><input type="number" step="0.01" min="0" name="nssa_ceiling" class="form-control" value="{{ old('nssa_ceiling', $val('nssa_ceiling')) }}" placeholder="No ceiling"></div>
                        <div class="col-4"><label class="form-label">in</label><select name="nssa_ceiling_currency" class="form-control">@foreach($currencies as $c)<option @selected(old('nssa_ceiling_currency', $val('nssa_ceiling_currency') ?? $currency) === $c)>{{ $c }}</option>@endforeach</select></div>
                        <div class="col-12"><small class="text-muted">A ceiling in another currency (NSSA sets it in USD) is converted at the pay-date rate.</small></div>
                        <div class="col-12"><label><input type="checkbox" name="nssa_deductible" value="1" @checked(old('nssa_deductible', $prefill ? $prefill['nssa_deductible'] : ($setting?->nssa_deductible ?? true)))> Employee NSSA reduces taxable income</label></div>
                        <div class="col-6"><label class="form-label">AIDS levy (% of PAYE)</label><input type="number" step="0.001" min="0" max="100" name="aids_levy_rate" class="form-control" required value="{{ old('aids_levy_rate', $val('aids_levy_rate')) }}"></div>
                        <div class="col-6"><label class="form-label">Medical aid credit (% of contributions)</label><input type="number" step="0.001" min="0" max="100" name="medical_aid_credit_rate" class="form-control" value="{{ old('medical_aid_credit_rate', $val('medical_aid_credit_rate')) }}"></div>
                    </div>
                </div>
                <div class="card">
                    <div class="header"><h2><strong>Other levies</strong></h2><small class="text-muted">% of gross pay, e.g. ZIMDEF or an industry (NEC) levy</small></div>
                    <div class="body table-responsive">
                        <table class="table table-sm align-middle" id="levies">
                            <thead><tr><th>Name</th><th>Rate %</th><th>Paid by</th><th>Payable account</th><th></th></tr></thead>
                            <tbody>
                            @foreach($levies ?: [['name' => '', 'rate' => '', 'payer' => 'employer', 'account_id' => null]] as $i => $levy)
                                <tr>
                                    <td><input name="levies[{{ $i }}][name]" class="form-control form-control-sm" value="{{ $levy['name'] ?? '' }}"></td>
                                    <td><input type="number" step="0.001" min="0" max="100" name="levies[{{ $i }}][rate]" class="form-control form-control-sm" value="{{ $levy['rate'] ?? '' }}"></td>
                                    <td><select name="levies[{{ $i }}][payer]" class="form-control form-control-sm"><option value="employer" @selected(($levy['payer'] ?? '') === 'employer')>Employer</option><option value="employee" @selected(($levy['payer'] ?? '') === 'employee')>Employee</option></select></td>
                                    <td><select name="levies[{{ $i }}][account_id]" class="form-control form-control-sm"><option value="">Deductions payable</option>@foreach($liabilityAccounts as $a)<option value="{{ $a->id }}" @selected(($levy['account_id'] ?? null) == $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></td>
                                    <td><button type="button" class="btn btn-sm btn-link text-danger remove-row" aria-label="Remove levy">✕</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-primary add-row" data-table="levies">Add levy</button>
                    </div>
                </div>
            </div>
        </div>
        @if($canEdit)<button class="btn btn-primary">Save {{ $year }} {{ $currency }} settings</button>@else<p class="text-muted">Only a Company Admin can change statutory settings.</p>@endif
        </fieldset>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    function renumber(tbody) { tbody.querySelectorAll('tr').forEach(function (row, i) { row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/\[\d+\]/, '[' + i + ']'); }); }); }
    document.querySelectorAll('.add-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tbody = document.querySelector('#' + btn.dataset.table + ' tbody'), row = tbody.querySelector('tr').cloneNode(true);
            row.querySelectorAll('input').forEach(function (el) { el.value = ''; });
            tbody.appendChild(row); renumber(tbody);
        });
    });
    document.addEventListener('click', function (e) {
        if (!e.target.classList.contains('remove-row')) return;
        var tbody = e.target.closest('tbody');
        if (tbody.querySelectorAll('tr').length > 1) { e.target.closest('tr').remove(); renumber(tbody); }
        else { e.target.closest('tr').querySelectorAll('input').forEach(function (el) { el.value = ''; }); }
    });
})();
</script>
@endpush

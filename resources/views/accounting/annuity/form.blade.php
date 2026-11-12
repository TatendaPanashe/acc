@extends('layouts.main')

@section('title', $contract->exists ? 'Edit contract' : 'New contract')

@section('content')
    @include('accounting.partials.page-header', ['title' => $contract->exists ? 'Edit '.$contract->contract_number : 'New recurring contract', 'section' => 'Annuity Billing'])
    @include('accounting.annuity._tabs')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @php
        $lines = old('lines', $contract->exists ? $contract->lines->map(fn ($l) => $l->only(['item_id', 'revenue_account_id', 'description', 'quantity', 'unit_price', 'tax_rate']))->all() : [['revenue_account_id' => $defaultRevenue, 'quantity' => 1, 'tax_rate' => 0]]);
    @endphp

    <form method="POST" action="{{ $contract->exists ? route('accounting.annuity-billing.contracts.update', $contract) : route('accounting.annuity-billing.contracts.store') }}">
        @csrf @if($contract->exists) @method('PUT') @endif
        <div class="card">
            <div class="header"><h2><strong>Contract</strong></h2></div>
            <div class="body row g-3">
                <div class="col-md-3"><label class="form-label">Contract number</label><input name="contract_number" class="form-control" required maxlength="40" value="{{ old('contract_number', $contract->contract_number) }}" placeholder="SUP-0001"></div>
                <div class="col-md-5"><label class="form-label">Customer</label><select name="customer_id" class="form-control" required><option value="">Choose…</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $contract->customer_id) == $customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Currency</label><select name="currency" class="form-control">@foreach($currencies as $code)<option @selected(old('currency', $contract->currency) === $code)>{{ $code }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Billing</label><select name="billing_cycle" class="form-control">@foreach(\App\Modules\Accounting\Models\Subscription::CYCLE_LABELS as $key => $label)<option value="{{ $key }}" @selected(old('billing_cycle', $contract->billing_cycle) === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" required maxlength="255" value="{{ old('description', $contract->description) }}" placeholder="Annual support and maintenance"></div>
                <div class="col-md-2"><label class="form-label">Starts (first invoice)</label><input type="date" name="starts_on" class="form-control" value="{{ old('starts_on', $contract->starts_on?->toDateString()) }}" @disabled($contract->exists) @required(!$contract->exists)></div>
                <div class="col-md-2"><label class="form-label">Term (months)</label><input type="number" name="term_months" min="1" max="120" class="form-control" value="{{ old('term_months', $contract->term_months) }}" placeholder="Open-ended"></div>
                <div class="col-md-2"><label class="form-label">Term ends</label><input type="date" name="ends_on" class="form-control" value="{{ old('ends_on', $contract->ends_on?->toDateString()) }}"><small class="text-muted">Blank: from start + term</small></div>
                <div class="col-md-2"><label class="form-label">Payment terms (days)</label><input type="number" name="payment_terms_days" min="0" max="365" class="form-control" value="{{ old('payment_terms_days', $contract->payment_terms_days) }}" required></div>
                <div class="col-md-2"><label class="form-label">Escalation on renewal %</label><input type="number" step="0.01" min="0" max="100" name="escalation_percent" class="form-control" value="{{ old('escalation_percent', $contract->escalation_percent ?? 0) }}"></div>
                <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
                    <label class="d-flex gap-2 align-items-center"><input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew', $contract->auto_renew))> Renew automatically at the end of the term</label>
                    @can('sales.post')<label class="d-flex gap-2 align-items-center"><input type="checkbox" name="post_invoices" value="1" @checked(old('post_invoices', $contract->post_invoices))> Post invoices automatically (scheduled runs)</label>@endcan
                </div>
                <div class="col-md-4"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $contract->notes) }}</textarea></div>
            </div>
        </div>

        <div class="card">
            <div class="header"><h2><strong>Billed</strong> each cycle</h2></div>
            <div class="body table-responsive">
                <table class="table align-middle" id="contract-lines">
                    <thead><tr><th style="width:18%">Item (optional)</th><th>Description</th><th style="width:18%">Revenue account</th><th style="width:9%">Qty</th><th style="width:11%">Unit price</th><th style="width:8%">Tax %</th><th class="text-end" style="width:10%">Amount</th><th></th></tr></thead>
                    <tbody>
                    @foreach($lines as $i => $line)
                        <tr>
                            <td><select name="lines[{{ $i }}][item_id]" class="form-control form-control-sm item-select"><option value="">—</option>@foreach($items as $item)<option value="{{ $item->id }}" data-name="{{ $item->name }}" data-price="{{ $item->selling_price }}" @selected(($line['item_id'] ?? null) == $item->id)>{{ $item->sku }} {{ $item->name }}</option>@endforeach</select></td>
                            <td><input name="lines[{{ $i }}][description]" class="form-control form-control-sm desc" required maxlength="255" value="{{ $line['description'] ?? '' }}"></td>
                            <td><select name="lines[{{ $i }}][revenue_account_id]" class="form-control form-control-sm" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(($line['revenue_account_id'] ?? $defaultRevenue) == $account->id)>{{ $account->code }} {{ $account->name }}</option>@endforeach</select></td>
                            <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.0001" min="0.0001" class="form-control form-control-sm qty" required value="{{ $line['quantity'] ?? 1 }}"></td>
                            <td><input name="lines[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="form-control form-control-sm price" required value="{{ $line['unit_price'] ?? '' }}"></td>
                            <td><input name="lines[{{ $i }}][tax_rate]" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" value="{{ $line['tax_rate'] ?? 0 }}"></td>
                            <td class="text-end amount">0.00</td>
                            <td><button type="button" class="btn btn-sm btn-link text-danger remove-line" aria-label="Remove line">✕</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="6" class="text-end fw-semibold">Recurring amount (before tax)</td><td class="text-end fw-bold" id="contract-total">0.00</td><td></td></tr></tfoot>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-line">Add line</button>
            </div>
        </div>
        <button class="btn btn-primary">{{ $contract->exists ? 'Save changes' : 'Create contract' }}</button>
        <a href="{{ $contract->exists ? route('accounting.annuity-billing.contracts.show', $contract) : route('accounting.annuity-billing.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.querySelector('#contract-lines tbody');
    function recalc() {
        var total = 0;
        body.querySelectorAll('tr').forEach(function (row) {
            var amount = (parseFloat(row.querySelector('.qty').value) || 0) * (parseFloat(row.querySelector('.price').value) || 0);
            row.querySelector('.amount').textContent = amount.toFixed(2); total += amount;
        });
        document.getElementById('contract-total').textContent = total.toFixed(2);
    }
    function renumber() {
        body.querySelectorAll('tr').forEach(function (row, i) {
            row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + i + ']'); });
        });
    }
    document.getElementById('add-line').addEventListener('click', function () {
        var row = body.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input').forEach(function (el) { el.value = el.classList.contains('qty') ? 1 : (el.name.indexOf('tax_rate') > -1 ? 0 : ''); });
        row.querySelector('.item-select').value = '';
        body.appendChild(row); renumber(); recalc();
    });
    body.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-line') && body.querySelectorAll('tr').length > 1) { e.target.closest('tr').remove(); renumber(); recalc(); }
    });
    body.addEventListener('change', function (e) {
        if (e.target.classList.contains('item-select')) {
            var opt = e.target.selectedOptions[0], row = e.target.closest('tr');
            if (opt && opt.dataset.name) { if (!row.querySelector('.desc').value) row.querySelector('.desc').value = opt.dataset.name; if (!row.querySelector('.price').value) row.querySelector('.price').value = opt.dataset.price; }
        }
        recalc();
    });
    body.addEventListener('input', recalc);
    recalc();
})();
</script>
@endpush

@csrf
@isset($budget->id) @method('PUT') @endisset
<div class="mb-3">
    <label class="form-label">Period</label>
    <select name="accounting_period_id" class="form-control" required>
        <option value="">Select period</option>
        @foreach($periods as $period)
            <option value="{{ $period->id }}" @selected(old('accounting_period_id', $budget->accounting_period_id ?? '') == $period->id)>{{ $period->name }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">Account</label>
    <select name="account_id" class="form-control" required>
        <option value="">Select account</option>
        @foreach($accounts as $account)
            <option value="{{ $account->id }}" @selected(old('account_id', $budget->account_id ?? '') == $account->id)>{{ $account->code }} - {{ $account->name }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control text-uppercase" value="{{ old('currency', $budget->currency ?? 'USD') }}" required></div>
<div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control text-end" value="{{ old('amount', $budget->amount ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control">{{ old('notes', $budget->notes ?? '') }}</textarea></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Budget</button>

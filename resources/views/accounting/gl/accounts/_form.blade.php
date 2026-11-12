@csrf
@isset($account) @method('PUT') @endisset
<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Code</label>
        <input name="code" class="form-control" value="{{ old('code', $account->code ?? '') }}" required>
    </div>
    <div class="col-md-5 mb-3">
        <label class="form-label">Name</label>
        <input name="name" class="form-control" value="{{ old('name', $account->name ?? '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Parent Account</label>
        <select name="parent_id" class="form-control">
            <option value="">No parent</option>
            @foreach($parentAccounts as $parent)
                <option value="{{ $parent->id }}" @selected(old('parent_id', $account->parent_id ?? '') == $parent->id)>
                    {{ $parent->code }} - {{ $parent->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Type</label>
        <select name="type" class="form-control" required>
            @foreach(['asset', 'liability', 'equity', 'revenue', 'expense'] as $type)
                <option value="{{ $type }}" @selected(old('type', $account->type ?? '') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Normal Balance</label>
        <select name="normal_balance" class="form-control" required>
            <option value="debit" @selected(old('normal_balance', $account->normal_balance ?? '') === 'debit')>Debit</option>
            <option value="credit" @selected(old('normal_balance', $account->normal_balance ?? '') === 'credit')>Credit</option>
        </select>
    </div>
    <div class="col-md-4 mb-3 d-flex align-items-end gap-3">
        <div class="form-check">
            <input type="checkbox" name="is_control_account" value="1" class="form-check-input" id="is_control_account" @checked(old('is_control_account', $account->is_control_account ?? false))>
            <label for="is_control_account" class="form-check-label">Control</label>
        </div>
        <div class="form-check">
            <input type="checkbox" name="allow_posting" value="1" class="form-check-input" id="allow_posting" @checked(old('allow_posting', $account->allow_posting ?? true))>
            <label for="allow_posting" class="form-check-label">Posting</label>
        </div>
        <div class="form-check">
            <input type="checkbox" name="status" value="1" class="form-check-input" id="status" @checked(old('status', $account->status ?? true))>
            <label for="status" class="form-check-label">Active</label>
        </div>
    </div>
</div>
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Account</button>

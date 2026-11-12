@csrf
@isset($invoice->id) @method('PUT') @endisset
<div class="row">
<div class="col-md-4 mb-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-control" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $invoice->supplier_id ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
<div class="col-md-2 mb-3"><label class="form-label">Invoice No.</label><input name="invoice_number" class="form-control" value="{{ old('invoice_number', $invoice->invoice_number ?? 'PINV-'.now()->format('Ymd-His')) }}" required></div>
<div class="col-md-2 mb-3"><label class="form-label">Date</label><input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d') ?? now()->toDateString()) }}" required></div>
<div class="col-md-2 mb-3"><label class="form-label">Due</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d') ?? now()->addDays(30)->toDateString()) }}" required></div>
<div class="col-md-2 mb-3"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control" value="{{ old('currency', $invoice->currency ?? 'USD') }}" required></div>
</div>
@php($lines = old('lines') ?: (($invoice->exists && $invoice->lines->isNotEmpty()) ? $invoice->lines->map(fn($l) => $l->only(['expense_account_id','description','quantity','unit_cost','tax_rate']))->toArray() : [['expense_account_id'=>'','description'=>'','quantity'=>1,'unit_cost'=>'','tax_rate'=>15]]))
<div class="table-responsive mb-3"><table class="table commercial-lines"><thead><tr><th>Expense/Asset Account</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Cost</th><th class="text-end">Tax %</th><th></th></tr></thead><tbody>
@foreach($lines as $i => $line)<tr><td><select name="lines[{{ $i }}][expense_account_id]" class="form-control" required><option value="">Select account</option>@foreach($expenseAccounts as $account)<option value="{{ $account->id }}" @selected(($line['expense_account_id'] ?? '') == $account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></td><td><input name="lines[{{ $i }}][description]" class="form-control" value="{{ $line['description'] ?? '' }}" required></td><td><input type="number" step="0.0001" name="lines[{{ $i }}][quantity]" class="form-control text-end" value="{{ $line['quantity'] ?? 1 }}" required></td><td><input type="number" step="0.01" name="lines[{{ $i }}][unit_cost]" class="form-control text-end" value="{{ $line['unit_cost'] ?? '' }}" required></td><td><input type="number" step="0.01" name="lines[{{ $i }}][tax_rate]" class="form-control text-end" value="{{ $line['tax_rate'] ?? 0 }}"></td><td><button type="button" class="btn btn-danger btn-sm line-remove"><i class="fas fa-trash"></i></button></td></tr>@endforeach
</tbody></table></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<button type="button" class="btn btn-secondary line-add"><i class="fas fa-plus"></i> Add Line</button> <button class="btn btn-primary"><i class="fas fa-save"></i> Save Draft</button>
@push('scripts')<script>(function(){document.querySelectorAll('.commercial-lines').forEach(function(t){function ren(){t.querySelectorAll('tbody tr').forEach(function(r,i){r.querySelectorAll('[name^="lines["]').forEach(function(el){el.name=el.name.replace(/lines\[\d+\]/,'lines['+i+']')})})}var add=document.querySelector('.line-add');if(add)add.onclick=function(){var c=t.querySelector('tbody tr').cloneNode(true);c.querySelectorAll('input').forEach(function(i){i.value=i.name.includes('quantity')?1:''});c.querySelectorAll('select').forEach(function(s){s.selectedIndex=0});t.querySelector('tbody').appendChild(c);ren()};t.onclick=function(e){if(!e.target.closest('.line-remove'))return;if(t.querySelectorAll('tbody tr').length>1)e.target.closest('tr').remove();ren()}})})();</script>@endpush
@canroute('accounting.ai-automation.suggest')
@push('scripts')
<script>
// GL auto-coding: suggest the expense account from the line description (and supplier).
(function () {
    var table = document.querySelector('.commercial-lines');
    if (!table) return;
    var url = @json(route('accounting.ai-automation.suggest')), timers = new WeakMap();
    function hintFor(row) {
        var cell = row.querySelector('select[name$="[expense_account_id]"]').parentNode, hint = cell.querySelector('.ai-hint');
        if (!hint) { hint = document.createElement('small'); hint.className = 'ai-hint d-block text-muted mt-1'; cell.appendChild(hint); }
        return hint;
    }
    function suggest(input) {
        var row = input.closest('tr'), select = row.querySelector('select[name$="[expense_account_id]"]'), hint = hintFor(row);
        var text = input.value.trim(), supplier = document.querySelector('select[name="supplier_id"]');
        if (text.length < 3) { hint.textContent = ''; return; }
        fetch(url + '?' + new URLSearchParams({ text: text, supplier_id: supplier ? supplier.value : '' }), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { suggestions: [] }; })
            .then(function (data) {
                var best = (data.suggestions || [])[0];
                hint.innerHTML = '';
                if (!best) return;
                var pct = Math.round(best.probability * 100);
                if (!select.value && pct >= 60) { select.value = String(best.account_id); }
                hint.append('Suggested: ' + best.code + ' ' + best.name + ' (' + pct + '%) ');
                if (select.value !== String(best.account_id)) {
                    var use = document.createElement('a'); use.href = '#'; use.textContent = 'Use';
                    use.onclick = function (e) { e.preventDefault(); select.value = String(best.account_id); use.remove(); };
                    hint.append(use);
                }
            }).catch(function () {});
    }
    table.addEventListener('input', function (e) {
        if (!e.target.matches('input[name$="[description]"]')) return;
        clearTimeout(timers.get(e.target));
        timers.set(e.target, setTimeout(function () { suggest(e.target); }, 450));
    });
})();
</script>
@endpush
@endcanroute

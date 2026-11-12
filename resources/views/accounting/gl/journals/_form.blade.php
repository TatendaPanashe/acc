@csrf
@isset($journal->id) @method('PUT') @endisset
<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Journal No.</label>
        <input name="entry_number" class="form-control" value="{{ old('entry_number', $journal->entry_number ?? 'JNL-'.now()->format('Ymd-His')) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Date</label>
        <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', optional($journal->entry_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Period</label>
        <input class="form-control" value="{{ $journal->accountingPeriod?->name ?? 'Assigned from the date' }}" disabled>
        <small class="text-muted">Only dates in an open period can be posted.</small>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Currency</label>
        <input name="currency" maxlength="3" class="form-control text-uppercase" value="{{ old('currency', $journal->currency ?? 'USD') }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Memo</label>
        <textarea name="memo" class="form-control">{{ old('memo', $journal->memo ?? '') }}</textarea>
    </div>
</div>

@php
    $oldLines = old('lines');
    $lines = $oldLines ?: (($journal->exists && $journal->lines->isNotEmpty())
        ? $journal->lines->map(fn ($line) => $line->only(['account_id', 'description', 'debit', 'credit']))->toArray()
        : [
            ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
            ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
        ]);
@endphp

<div class="table-responsive mb-3">
    <table class="table" id="journal-lines-table">
        <thead>
            <tr>
                <th style="min-width: 260px;">Account</th>
                <th>Description</th>
                <th class="text-end" style="width: 150px;">Debit</th>
                <th class="text-end" style="width: 150px;">Credit</th>
                <th style="width: 70px;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $index => $line)
                <tr>
                    <td>
                        <select name="lines[{{ $index }}][account_id]" class="form-control" required>
                            <option value="">Select account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected(($line['account_id'] ?? '') == $account->id)>
                                    {{ $account->code }} - {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td><input name="lines[{{ $index }}][description]" class="form-control" value="{{ $line['description'] ?? '' }}"></td>
                    <td><input type="number" step="0.01" min="0" name="lines[{{ $index }}][debit]" class="form-control text-end journal-debit" value="{{ $line['debit'] ?? '' }}"></td>
                    <td><input type="number" step="0.01" min="0" name="lines[{{ $index }}][credit]" class="form-control text-end journal-credit" value="{{ $line['credit'] ?? '' }}"></td>
                    <td><button type="button" class="btn btn-danger btn-sm journal-remove"><i class="fas fa-trash"></i></button></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-end">Totals</th>
                <th class="text-end" id="journal-debit-total">0.00</th>
                <th class="text-end" id="journal-credit-total">0.00</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-secondary" id="journal-add-line"><i class="fas fa-plus"></i> Add Line</button>
    <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Draft</button>
    <a href="{{ route('accounting.gl.journals.index') }}" class="btn btn-light">Cancel</a>
</div>

@push('scripts')
<script>
    (function () {
        var table = document.getElementById('journal-lines-table');
        var addButton = document.getElementById('journal-add-line');
        if (!table || !addButton) return;

        function money(value) {
            return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function recalc() {
            var debit = 0;
            var credit = 0;
            table.querySelectorAll('.journal-debit').forEach(function (input) { debit += Number(input.value || 0); });
            table.querySelectorAll('.journal-credit').forEach(function (input) { credit += Number(input.value || 0); });
            document.getElementById('journal-debit-total').textContent = money(debit);
            document.getElementById('journal-credit-total').textContent = money(credit);
        }

        function renumber() {
            table.querySelectorAll('tbody tr').forEach(function (row, index) {
                row.querySelectorAll('[name^="lines["]').forEach(function (input) {
                    input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
                });
            });
        }

        addButton.addEventListener('click', function () {
            var first = table.querySelector('tbody tr');
            var clone = first.cloneNode(true);
            clone.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
            table.querySelector('tbody').appendChild(clone);
            renumber();
            recalc();
        });

        table.addEventListener('click', function (event) {
            if (!event.target.closest('.journal-remove')) return;
            if (table.querySelectorAll('tbody tr').length <= 2) return;
            event.target.closest('tr').remove();
            renumber();
            recalc();
        });

        table.addEventListener('input', recalc);
        recalc();
    })();
</script>
@endpush

@extends('layouts.main')

@section('title', 'Pay items')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Pay items', 'section' => 'Payroll'])
    @include('payroll._tabs')

    @php $P = \App\Modules\Payroll\Models\PayItem::class; @endphp
    <div class="card">
        <div class="body small">
            <strong>Earnings</strong> add to gross pay (untick "Taxable" for exempt items). <strong>Deductions</strong> come off net pay; tick "Before tax" for deductions that reduce taxable income, such as an approved pension fund.
            Deductions in the "Medical aid" category earn the medical aid tax credit set in the statutory settings. Leave the GL account blank to use the payroll accounts in Account Mapping.
        </div>
    </div>

    <div class="card">
        <div class="body table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Category</th><th>Calculation</th><th>Default</th><th>Taxable / before tax</th><th>GL account</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td><input name="code" form="item-{{ $item->id }}" class="form-control form-control-sm" value="{{ $item->code }}" required></td>
                        <td><input name="name" form="item-{{ $item->id }}" class="form-control form-control-sm" value="{{ $item->name }}" required></td>
                        <td><input type="hidden" name="type" form="item-{{ $item->id }}" value="{{ $item->type }}">{{ ucfirst($item->type) }}</td>
                        <td><select name="category" form="item-{{ $item->id }}" class="form-control form-control-sm">@foreach($item->type === 'deduction' ? $P::DEDUCTION_CATEGORIES : $P::EARNING_CATEGORIES as $k => $l)<option value="{{ $k }}" @selected($item->category === $k)>{{ $l }}</option>@endforeach</select></td>
                        <td><select name="calculation" form="item-{{ $item->id }}" class="form-control form-control-sm">@foreach($P::CALCULATIONS as $k => $l)<option value="{{ $k }}" @selected($item->calculation === $k)>{{ $l }}</option>@endforeach</select></td>
                        <td><input type="number" step="0.0001" min="0" name="default_amount" form="item-{{ $item->id }}" class="form-control form-control-sm" value="{{ (float) $item->default_amount }}"></td>
                        <td>@if($item->type === 'earning')<label><input type="checkbox" name="taxable" value="1" form="item-{{ $item->id }}" @checked($item->taxable)> Taxable</label>@else<label><input type="checkbox" name="pre_tax" value="1" form="item-{{ $item->id }}" @checked($item->pre_tax)> Before tax</label>@endif</td>
                        <td><select name="gl_account_id" form="item-{{ $item->id }}" class="form-control form-control-sm"><option value="">Default</option>@foreach($accounts->where('type', $item->type === 'earning' ? 'expense' : 'liability') as $a)<option value="{{ $a->id }}" @selected($item->gl_account_id === $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></td>
                        <td><input type="hidden" name="status" value="0" form="item-{{ $item->id }}"><input type="checkbox" name="status" value="1" form="item-{{ $item->id }}" @checked($item->status)></td>
                        <td>@canroute('accounting.payroll.items.update', 'PUT')<button class="btn btn-sm btn-primary" form="item-{{ $item->id }}">Save</button>@endcanroute</td>
                    </tr>
                @endforeach
                @if($items->isEmpty())<tr><td colspan="10" class="text-muted">No pay items yet. Typical ones: housing and transport allowances, overtime, bonus, pension, medical aid, loan repayments.</td></tr>@endif
                </tbody>
            </table>
            @foreach($items as $item)<form method="POST" action="{{ route('accounting.payroll.items.update', $item) }}" id="item-{{ $item->id }}">@csrf @method('PUT')</form>@endforeach

            @canroute('accounting.payroll.items.store', 'POST')
                <h6 class="mt-4">Add a pay item</h6>
                <form method="POST" action="{{ route('accounting.payroll.items.store') }}" class="row g-2 align-items-end" id="new-item">
                    @csrf
                    <div class="col-md-1"><label class="form-label">Code</label><input name="code" class="form-control form-control-sm" required placeholder="HOUSING"></div>
                    <div class="col-md-2"><label class="form-label">Name</label><input name="name" class="form-control form-control-sm" required placeholder="Housing allowance"></div>
                    <div class="col-md-1"><label class="form-label">Type</label><select name="type" class="form-control form-control-sm" id="new-type"><option value="earning">Earning</option><option value="deduction">Deduction</option></select></div>
                    <div class="col-md-2"><label class="form-label">Category</label><select name="category" class="form-control form-control-sm" id="new-category"></select></div>
                    <div class="col-md-2"><label class="form-label">Calculation</label><select name="calculation" class="form-control form-control-sm">@foreach($P::CALCULATIONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-1"><label class="form-label">Default</label><input type="number" step="0.0001" min="0" name="default_amount" class="form-control form-control-sm" placeholder="0"></div>
                    <div class="col-md-2"><label class="d-block"><input type="checkbox" name="taxable" value="1" checked> Taxable (earnings)</label><label class="d-block"><input type="checkbox" name="pre_tax" value="1"> Before tax (deductions)</label></div>
                    <div class="col-md-1"><input type="hidden" name="status" value="1"><button class="btn btn-sm btn-primary w-100">Add</button></div>
                </form>
            @endcanroute
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var cats = { earning: @json($P::EARNING_CATEGORIES), deduction: @json($P::DEDUCTION_CATEGORIES) };
    var type = document.getElementById('new-type'), cat = document.getElementById('new-category');
    if (!type) return;
    function fill() { cat.innerHTML = ''; Object.entries(cats[type.value]).forEach(function (e) { var o = document.createElement('option'); o.value = e[0]; o.textContent = e[1]; cat.appendChild(o); }); }
    type.addEventListener('change', fill); fill();
})();
</script>
@endpush

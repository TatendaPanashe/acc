@include('accounting.partials.tabs', ['tabs' => [
    ['Overview', 'accounting.payroll.index', 'accounting.payroll.index'],
    ['Payroll runs', 'accounting.payroll.runs.index', 'accounting.payroll.runs.*'],
    ['Leave & overtime', 'accounting.payroll.requests.index', 'accounting.payroll.requests.*'],
    ['Employees', 'accounting.payroll.employees.index', 'accounting.payroll.employees.*'],
    ['Pay items', 'accounting.payroll.items.index', 'accounting.payroll.items.*'],
    ['Statutory settings', 'accounting.payroll.settings.index', 'accounting.payroll.settings.*'],
]])
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

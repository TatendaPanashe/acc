@include('accounting.partials.tabs', ['tabs' => [
    ['Overview', 'accounting.bom-job-costing.index', 'accounting.bom-job-costing.index'],
    ['Bills of materials', 'accounting.bom-job-costing.boms.index', 'accounting.bom-job-costing.boms.*'],
    ['Work orders', 'accounting.bom-job-costing.work-orders.index', 'accounting.bom-job-costing.work-orders.*'],
    ['Jobs', 'accounting.bom-job-costing.jobs.index', 'accounting.bom-job-costing.jobs.*'],
]])
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

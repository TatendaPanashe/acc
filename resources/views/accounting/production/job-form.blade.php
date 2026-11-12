@extends('layouts.main')

@section('title', $job->exists ? 'Edit job' : 'New job')

@section('content')
    @include('accounting.partials.page-header', ['title' => $job->exists ? 'Edit '.$job->job_number : 'New job', 'section' => 'BOM & Job Costing'])
    @include('accounting.production._tabs')

    <form method="POST" action="{{ $job->exists ? route('accounting.bom-job-costing.jobs.update', $job) : route('accounting.bom-job-costing.jobs.store') }}">
        @csrf @if($job->exists) @method('PUT') @endif
        <div class="card">
            <div class="header"><h2><strong>Job</strong></h2></div>
            <div class="body row g-3">
                <div class="col-md-2"><label class="form-label">Job number</label><input name="job_number" class="form-control" required maxlength="40" value="{{ old('job_number', $job->job_number) }}"></div>
                <div class="col-md-5"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="255" value="{{ old('name', $job->name) }}" placeholder="Install borehole pump at Ruwa site"></div>
                <div class="col-md-3"><label class="form-label">Customer</label><select name="customer_id" class="form-control"><option value="">Internal job</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $job->customer_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Due</label><input type="date" name="due_on" class="form-control" value="{{ old('due_on', $job->due_on?->toDateString()) }}"></div>
            </div>
        </div>
        <div class="card">
            <div class="header"><h2><strong>Budget</strong> (base currency)</h2></div>
            <div class="body row g-3">
                @foreach(['budget_materials' => 'Materials', 'budget_labour' => 'Labour', 'budget_overhead' => 'Overhead', 'budget_other' => 'Subcontract & other'] as $field => $label)
                    <div class="col-md-2"><label class="form-label">{{ $label }}</label><input type="number" step="0.01" min="0" name="{{ $field }}" class="form-control" value="{{ old($field, (float) $job->$field) }}"></div>
                @endforeach
                <div class="col-md-3"><label class="form-label">Flag for review when over budget by (%)</label><input type="number" step="0.01" min="0" max="100" name="variance_tolerance" class="form-control" value="{{ old('variance_tolerance', (float) $job->variance_tolerance) }}"></div>
            </div>
        </div>
        <div class="card">
            <div class="header"><h2><strong>Billing</strong></h2></div>
            <div class="body row g-3">
                <div class="col-md-3"><label class="form-label">Method</label><select name="billing_method" class="form-control"><option value="fixed" @selected(old('billing_method', $job->billing_method) === 'fixed')>Fixed price</option><option value="cost_plus" @selected(old('billing_method', $job->billing_method) === 'cost_plus')>Cost plus mark-up</option></select></div>
                <div class="col-md-3"><label class="form-label">Contract value (fixed price)</label><input type="number" step="0.01" min="0" name="contract_value" class="form-control" value="{{ old('contract_value', (float) $job->contract_value) }}"></div>
                <div class="col-md-3"><label class="form-label">Mark-up % (cost plus)</label><input type="number" step="0.01" min="0" name="markup_percent" class="form-control" value="{{ old('markup_percent', (float) $job->markup_percent) }}"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $job->notes) }}</textarea></div>
            </div>
        </div>
        <button class="btn btn-primary">{{ $job->exists ? 'Save job' : 'Create job' }}</button>
    </form>
@endsection

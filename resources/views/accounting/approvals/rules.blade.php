@extends('layouts.main')

@section('title', 'Approval rules')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Approval rules', 'section' => 'Approvals & Audit'])
    @include('accounting.approvals._tabs')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="body">
            <p class="mb-2">Without rules, any Finance Manager or Company Admin can approve any document. Rules add two controls:</p>
            <ul class="mb-2">
                <li><strong>Thresholds:</strong> from a given amount (in base currency), only a Company Admin may approve.</li>
                <li><strong>Segregation of duties:</strong> the person who captured a document cannot approve it.</li>
            </ul>
            <p class="text-muted small mb-0">For each document, the rule with the highest "from amount" not above the document's amount applies; a rule for a specific document type wins over an "All documents" rule with the same amount. Sales invoices and receipts are posted by accountants and are not covered.</p>
        </div>
    </div>

    <div class="card">
        <div class="header"><h2><strong>Rules</strong></h2></div>
        <div class="body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Document</th><th>From amount</th><th>Who may approve</th><th>Preparer cannot approve</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td><select name="document_type" form="rule-{{ $rule->id }}" class="form-control form-control-sm">@foreach($types as $key => $label)<option value="{{ $key }}" @selected($rule->document_type === $key)>{{ $label }}</option>@endforeach</select></td>
                        <td><input name="min_amount" form="rule-{{ $rule->id }}" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $rule->min_amount }}"></td>
                        <td><select name="approver_role" form="rule-{{ $rule->id }}" class="form-control form-control-sm">@foreach($roles as $key => $label)<option value="{{ $key }}" @selected($rule->approver_role === $key)>{{ $label }}</option>@endforeach</select></td>
                        <td><input type="checkbox" name="require_different_user" value="1" form="rule-{{ $rule->id }}" @checked($rule->require_different_user)></td>
                        <td><input type="checkbox" name="status" value="1" form="rule-{{ $rule->id }}" @checked($rule->status)></td>
                        <td class="text-end text-nowrap">
                            @canroute('accounting.approvals-audit.rules.update', 'PUT')
                                <button class="btn btn-sm btn-primary" form="rule-{{ $rule->id }}">Save</button>
                                <form method="POST" action="{{ route('accounting.approvals-audit.rules.destroy', $rule) }}" class="d-inline" onsubmit="return confirm('Remove this rule?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
                            @endcanroute
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No rules yet: Finance Managers and Company Admins can approve everything.</td></tr>
                @endforelse
                </tbody>
            </table>
            @foreach($rules as $rule)
                <form method="POST" action="{{ route('accounting.approvals-audit.rules.update', $rule) }}" id="rule-{{ $rule->id }}">@csrf @method('PUT')</form>
            @endforeach

            @canroute('accounting.approvals-audit.rules.store', 'POST')
                <h6 class="mt-4">Add a rule</h6>
                <form method="POST" action="{{ route('accounting.approvals-audit.rules.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3"><label class="form-label">Document</label><select name="document_type" class="form-control">@foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label">From amount</label><input name="min_amount" type="number" step="0.01" min="0" value="0" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Who may approve</label><select name="approver_role" class="form-control">@foreach($roles as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="d-flex gap-2 align-items-center"><input type="checkbox" name="require_different_user" value="1" checked> Preparer cannot approve</label><input type="hidden" name="status" value="1"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Add rule</button></div>
                </form>
            @endcanroute
        </div>
    </div>
@endsection

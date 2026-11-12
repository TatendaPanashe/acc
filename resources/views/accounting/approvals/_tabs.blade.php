@include('accounting.partials.tabs', ['tabs' => [
    ['Approval inbox', 'accounting.approvals-audit.index', 'accounting.approvals-audit.index'],
    ['Approval rules', 'accounting.approvals-audit.rules.index', 'accounting.approvals-audit.rules.*'],
    ['Audit trail', 'accounting.approvals-audit.audit.index', 'accounting.approvals-audit.audit.*'],
]])

@include('accounting.partials.tabs', ['tabs' => [
    ['Contracts', 'accounting.annuity-billing.index', 'accounting.annuity-billing.index|accounting.annuity-billing.contracts.*'],
    ['Billing run', 'accounting.annuity-billing.runs.preview', 'accounting.annuity-billing.runs.*'],
]])

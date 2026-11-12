@include('accounting.partials.tabs', ['tabs' => [
    ['Dashboard', 'accounting.reporting.index', 'accounting.reporting.index'],
    ['Report library', 'accounting.reporting.library', 'accounting.reporting.library|accounting.reporting.run|accounting.reporting.saved.*'],
]])

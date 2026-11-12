@include('accounting.partials.tabs', ['tabs' => [
    ['Overview', 'accounting.ai-automation.index', 'accounting.ai-automation.index'],
    ['Forecasts', 'accounting.ai-automation.forecasts.index', 'accounting.ai-automation.forecasts.*'],
    ['Cash & collections', 'accounting.ai-automation.collections', 'accounting.ai-automation.collections'],
    ['Anomaly detection', 'accounting.ai-automation.insights.index', 'accounting.ai-automation.insights.*'],
    ['Auto-coding', 'accounting.ai-automation.auto-coding', 'accounting.ai-automation.auto-coding'],
]])
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

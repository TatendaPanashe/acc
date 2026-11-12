@extends('layouts.main')

@section('title', 'Accounting Dashboard')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Accounting Dashboard'])

    <div class="row">
        @foreach($metrics as $metric)
            <div class="col-xl-3 col-sm-6">
                <div class="card {{ $metric['colour'] }}">
                    <div class="info-box-5 p-4">
                        <div class="card-icon card-icon-large"><i data-feather="{{ $metric['icon'] }}"></i></div>
                        <div class="mb-4">
                            <h5 class="font-20 mb-0">{{ $metric['label'] }}</h5>
                        </div>
                        <h2 class="d-flex align-items-center mb-0">{{ number_format($metric['value']) }}</h2>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="header">
                    <h2><strong>Accounting</strong> Modules</h2>
                </div>
                <div class="body">
                    <div class="row acc-module-grid">
                        @php
                            $permissions = app(\App\Services\Authorization\RoutePermissions::class);
                            $moduleRoutes = [
                                'general-ledger' => 'accounting.gl.journals.index',
                                'accounts-receivable' => 'accounting.ar.invoices.index',
                                'accounts-payable' => 'accounting.ap.supplier-invoices.index',
                                'inventory-order-entry' => 'accounting.inventory.overview',
                                'banking' => 'accounting.banking.index',
                                'fixed-assets' => 'accounting.fixed-assets.index',
                                'reporting-analytics' => 'accounting.reporting.index',
                                'crm' => 'accounting.crm.index',
                                'bom-job-costing' => 'accounting.bom-job-costing.index',
                                'point-of-sale' => 'accounting.pos.create',
                                'annuity-billing' => 'accounting.annuity-billing.index',
                                'approvals-audit' => 'accounting.approvals-audit.index',
                                'ai-automation' => 'accounting.ai-automation.index',
                                'client-portal' => 'accounting.client-portal.index',
                                'revenue-recognition' => 'accounting.revenue-recognition.index',
                                'einvoicing-tax-mesh' => 'accounting.einvoicing-tax-mesh.index',
                                'esg-carbon' => 'accounting.esg-carbon.index',
                            ];
                        @endphp
                        @foreach(config('accounting.modules') as $key => $module)
                            @php
                                $routeName = $moduleRoutes[$key] ?? 'accounting.modules.show';
                                $allowed = $permissions->allows(auth()->user(), $routeName, 'GET', $key);
                            @endphp
                            @continue(!$allowed)
                            <div class="col-xl-4 col-md-6">
                                <a href="{{ isset($moduleRoutes[$key]) ? route($routeName) : route('accounting.modules.show', $key) }}" class="acc-module-tile">
                                    <span class="acc-module-icon"><i data-feather="{{ $module['icon'] }}"></i></span>
                                    <span>
                                        <strong>{{ $module['title'] }}</strong>
                                        <small>{{ $module['description'] }}</small>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if($approvalQueue)
                <div class="card">
                    <div class="header">
                        <h2>@can('approve')<strong>Waiting for</strong> your approval @else<strong>Awaiting</strong> manager approval @endcan</h2>
                    </div>
                    <div class="body">
                        <ul class="list-group list-group-flush">
                            @foreach($approvalQueue as $item)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                                    <span class="badge bg-warning text-dark">{{ $item['count'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="header">
                    <h2><strong>Recent</strong> Journals</h2>
                </div>
                <div class="body table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Status</th>
                                <th class="text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentJournals as $journal)
                                <tr>
                                    <td>{{ $journal->entry_number }}</td>
                                    <td><span class="badge col-blue">{{ ucfirst(str_replace('_', ' ', $journal->status)) }}</span></td>
                                    <td class="text-end">{{ $journal->entry_date?->format('Y-m-d') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">No journals yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

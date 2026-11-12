@php
    $permissions = app(\App\Services\Authorization\RoutePermissions::class);
    $user = auth()->user();
    $can = fn (string $route) => $permissions->allows($user, $route);

    // [label, route, active pattern]
    $groups = [
        'ACCOUNTING' => [
            ['General Ledger', 'book-open', 'accounting.gl.*', [
                ['Journal Entries', 'accounting.gl.journals.index', 'accounting.gl.journals.*'],
                ['Chart of Accounts', 'accounting.gl.accounts.index', 'accounting.gl.accounts.*'],
                ['Fiscal Years', 'accounting.gl.fiscal-years.index', 'accounting.gl.fiscal-years.*'],
                ['Accounting Periods', 'accounting.gl.periods.index', 'accounting.gl.periods.*'],
                ['Budgets', 'accounting.gl.budgets.index', 'accounting.gl.budgets.*'],
            ]],
            ['Accounts Receivable', 'file-plus', 'accounting.ar.*', [
                ['Customers', 'accounting.ar.customers.index', 'accounting.ar.customers.*'],
                ['Invoices', 'accounting.ar.invoices.index', 'accounting.ar.invoices.*'],
                ['Credit Notes', 'accounting.ar.credit-notes.index', 'accounting.ar.credit-notes.*'],
                ['Receipts', 'accounting.ar.receipts.index', 'accounting.ar.receipts.*'],
                ['Aging & Dunning', 'accounting.ar.aging.index', 'accounting.ar.aging.*'],
            ]],
            ['Accounts Payable', 'file-minus', 'accounting.ap.*', [
                ['Suppliers', 'accounting.ap.suppliers.index', 'accounting.ap.suppliers.*'],
                ['Supplier Invoices', 'accounting.ap.supplier-invoices.index', 'accounting.ap.supplier-invoices.*'],
                ['Supplier Payments', 'accounting.ap.payments.index', 'accounting.ap.payments.*'],
                ['Payment Runs', 'accounting.ap.payment-runs.index', 'accounting.ap.payment-runs.*'],
                ['Supplier Aging', 'accounting.ap.aging.index', 'accounting.ap.aging.*'],
            ]],
            ['Inventory & Orders', 'package', 'accounting.inventory.*', [
                ['Stock Overview', 'accounting.inventory.overview', 'accounting.inventory.overview'],
                ['Items', 'accounting.inventory.items.index', 'accounting.inventory.items.*'],
                ['Warehouses', 'accounting.inventory.warehouses.index', 'accounting.inventory.warehouses.*'],
                ['Sales Orders', 'accounting.inventory.sales-orders.index', 'accounting.inventory.sales-orders.*'],
                ['Purchase Orders', 'accounting.inventory.purchase-orders.index', 'accounting.inventory.purchase-orders.*'],
                ['Transfer Orders', 'accounting.inventory.transfer-orders.index', 'accounting.inventory.transfer-orders.*'],
                ['Stores Requisitions', 'accounting.inventory.stores-requisitions.index', 'accounting.inventory.stores-requisitions.*'],
            ]],
            ['Point of Sale', 'shopping-cart', 'accounting.pos.*', [
                ['POS Terminal', 'accounting.pos.create', 'accounting.pos.create'],
                ['Sales Register', 'accounting.pos.index', 'accounting.pos.index'],
            ]],
        ],
        'REPORTS' => [
            ['Financial Reports', 'bar-chart-2', 'accounting.gl.trial-balance|accounting.gl.profit-and-loss|accounting.gl.balance-sheet|accounting.gl.cash-flow|accounting.gl.statutory.*', [
                ['Trial Balance', 'accounting.gl.trial-balance', 'accounting.gl.trial-balance'],
                ['Profit & Loss', 'accounting.gl.profit-and-loss', 'accounting.gl.profit-and-loss'],
                ['Balance Sheet', 'accounting.gl.balance-sheet', 'accounting.gl.balance-sheet'],
                ['Cash Flow', 'accounting.gl.cash-flow', 'accounting.gl.cash-flow'],
                ['Statutory XLS Reports', 'accounting.gl.statutory.index', 'accounting.gl.statutory.*'],
            ]],
        ],
        'OPERATIONS' => [
            ['Enterprise Modules', 'grid', 'accounting.banking.*|accounting.fixed-assets.*|accounting.reporting.*|accounting.crm.*|accounting.approvals-audit.*|accounting.ai-automation.*|accounting.client-portal.*|accounting.revenue-recognition.*|accounting.einvoicing-tax-mesh.*|accounting.esg-carbon.*', [
                ['Bank Reconciliation', 'accounting.banking.index', 'accounting.banking.*'],
                ['Fixed Assets', 'accounting.fixed-assets.index', 'accounting.fixed-assets.index'],
                ['Depreciation Runs', 'accounting.fixed-assets.depreciation.index', 'accounting.fixed-assets.depreciation.*'],
                ['Reporting & Analytics', 'accounting.reporting.index', 'accounting.reporting.*'],
                ['CRM', 'accounting.crm.index', 'accounting.crm.*'],
                ['Approvals & Audit', 'accounting.approvals-audit.index', 'accounting.approvals-audit.*'],
                ['AI/ML Automation', 'accounting.ai-automation.index', 'accounting.ai-automation.*'],
                ['Client Portal', 'accounting.client-portal.index', 'accounting.client-portal.*'],
                ['ASC 606 Revenue', 'accounting.revenue-recognition.index', 'accounting.revenue-recognition.*'],
                ['E-Invoicing Tax Mesh', 'accounting.einvoicing-tax-mesh.index', 'accounting.einvoicing-tax-mesh.*'],
                ['ESG Carbon', 'accounting.esg-carbon.index', 'accounting.esg-carbon.*'],
            ]],
            ['BOM & Job Costing', 'layers', 'accounting.bom-job-costing.*', 'accounting.bom-job-costing.index'],
            ['Annuity Billing', 'repeat', 'accounting.annuity-billing.*', 'accounting.annuity-billing.index'],
        ],
        'COMPANY' => [
            ['Team', 'users', 'company.team.*', 'company.team.index'],
            ['Billing & Plan', 'credit-card', 'billing.*', 'billing.index'],
            ['Company Profile', 'briefcase', 'company.setup.*', 'company.setup.edit'],
            ['Accounting Settings', 'settings', 'accounting.settings.*', 'accounting.settings.index'],
        ],
    ];

    $isActive = fn (string $patterns) => request()->routeIs(...explode('|', $patterns));

    // Drop links the user cannot open, then empty menus and sections.
    $visible = [];
    foreach ($groups as $header => $items) {
        foreach ($items as $item) {
            [$label, $icon, $active, $target] = $item;
            if (is_array($target)) {
                $target = array_values(array_filter($target, fn ($link) => $can($link[1])));
                if ($target) {
                    $visible[$header][] = [$label, $icon, $active, $target];
                }
            } elseif ($can($target)) {
                $visible[$header][] = $item;
            }
        }
    }
@endphp

<aside id="leftsidebar" class="sidebar">
    <div class="menu">
        <ul class="list">
            <li class="sidebar-user-panel">
                <div class="user-panel">
                    <div class="image">
                        <img src="{{ asset('assets/images/redan.png') }}" class="user-img-style" alt="User Image">
                    </div>
                </div>
                <div class="profile-usertitle">
                    <div class="sidebar-userpic-name">{{ $user->name ?? 'Accounting User' }}</div>
                    <div class="profile-usertitle-job">{{ $user->company?->name ?? 'System Administration' }}</div>
                    <div class="profile-usertitle-job"><span class="badge bg-info">{{ $user?->roleLabel() }}</span></div>
                </div>
            </li>

            @if($can('dashboard'))
                <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}"><i data-feather="home"></i><span>Dashboard</span></a>
                </li>
            @endif

            @foreach($visible as $header => $items)
                <li class="header">{{ $header }}</li>
                @foreach($items as [$label, $icon, $active, $target])
                    @if(is_array($target))
                        <li class="{{ $isActive($active) ? 'active' : '' }}">
                            <a href="#" onclick="return false;" class="menu-toggle">
                                <i data-feather="{{ $icon }}"></i><span>{{ $label }}</span>
                            </a>
                            <ul class="ml-menu">
                                @foreach($target as [$linkLabel, $linkRoute, $linkActive])
                                    <li class="{{ $isActive($linkActive) ? 'active' : '' }}"><a href="{{ route($linkRoute) }}">{{ $linkLabel }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="{{ $isActive($active) ? 'active' : '' }}">
                            <a href="{{ route($target) }}"><i data-feather="{{ $icon }}"></i><span>{{ $label }}</span></a>
                        </li>
                    @endif
                @endforeach
            @endforeach

            @if($user?->is_superuser)
                <li class="header">SYSTEM</li>
                <li class="{{ request()->routeIs('system.*') ? 'active' : '' }}">
                    <a href="#" onclick="return false;" class="menu-toggle">
                        <i data-feather="shield"></i><span>System Management</span>
                    </a>
                    <ul class="ml-menu">
                        <li><a href="{{ route('system.companies.index') }}">Companies</a></li>
                        <li><a href="{{ route('system.users.index') }}">Users & Assignments</a></li>
                    </ul>
                </li>
            @endif
        </ul>
    </div>
</aside>

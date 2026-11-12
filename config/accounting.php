<?php

return [
    'modules' => [
        'general-ledger' => [
            'title' => 'General Ledger',
            'icon' => 'book-open',
            'description' => 'Double-entry journals, chart of accounts, budgets, trial balance, and financial statements.',
            'capabilities' => ['Chart of accounts', 'Journal posting', 'Budgets', 'Trial balance', 'P&L', 'Balance sheet', 'Cash flow'],
        ],
        'accounts-receivable' => [
            'title' => 'Accounts Receivable',
            'icon' => 'file-plus',
            'description' => 'Customer invoicing, credit notes, receipts, allocations, aging, dunning, and credit control.',
            'capabilities' => ['Invoices', 'Credit notes', 'Receipts', 'Allocations', 'Aging reports', 'Dunning letters', 'Credit limits'],
        ],
        'accounts-payable' => [
            'title' => 'Accounts Payable',
            'icon' => 'file-minus',
            'description' => 'Supplier invoices, PO matching, payment runs, remittances, and supplier aging.',
            'capabilities' => ['Supplier invoices', 'Payment runs', 'Cheque/EFT batches', 'PO reconciliation', 'Aging reports'],
        ],
        'inventory-order-entry' => [
            'title' => 'Inventory & Order Entry',
            'icon' => 'package',
            'description' => 'Multi-warehouse stock, serial/lot tracking, sales orders, purchase orders, transfers, and reorder alerts.',
            'capabilities' => ['Warehouses', 'Items', 'Lots/serials', 'Sales orders', 'Purchase orders', 'Transfer orders', 'Reorder alerts'],
        ],
        'banking' => [
            'title' => 'Bank Statement Manager',
            'icon' => 'credit-card',
            'description' => 'MT940, CAMT, and CSV statement imports with reconciliation against GL, AR, and AP transactions.',
            'capabilities' => ['Statement imports', 'Matching rules', 'Auto-reconciliation', 'Manual reconciliation', 'Bank journals'],
        ],
        'fixed-assets' => [
            'title' => 'Fixed Assets',
            'icon' => 'briefcase',
            'description' => 'Asset register, depreciation, disposals, transfers, and maintenance schedules.',
            'capabilities' => ['Asset register', 'Straight-line depreciation', 'Declining balance', 'Disposals', 'Transfers', 'Maintenance'],
        ],
        'reporting-analytics' => [
            'title' => 'Reporting & Analytics',
            'icon' => 'bar-chart-2',
            'description' => 'Financial report packs, Excel exports, dashboards, saved filters, and drill-down views.',
            'capabilities' => ['Report builder', 'Excel dashboards', 'PDF packs', 'Drill-down', 'Budget vs actual', 'Tax reporting'],
        ],
        'crm' => [
            'title' => 'CRM',
            'icon' => 'users',
            'description' => 'Customer contacts, activity history, support tickets, pipeline, and opportunities.',
            'capabilities' => ['Contacts', 'Interactions', 'Tickets', 'Sales pipeline', 'Opportunities', 'Quote conversion'],
        ],
        'bom-job-costing' => [
            'title' => 'BOM & Job Costing',
            'icon' => 'layers',
            'description' => 'Optional manufacturing and project-cost add-on for BOMs, work orders, labour, machine hours, and variance analysis.',
            'capabilities' => ['Multi-level BOM', 'Work orders', 'Labour hours', 'Machine hours', 'Job ledger', 'Variance analysis'],
        ],
        'point-of-sale' => [
            'title' => 'Point of Sale',
            'icon' => 'shopping-cart',
            'description' => 'Optional POS add-on with barcode scanning, cash drawer, offline mode, receipt printing, and inventory sync.',
            'capabilities' => ['Cash drawer', 'Barcode scanning', 'Offline queue', 'Receipt printing', 'Inventory sync', 'Cash-up'],
        ],
        'annuity-billing' => [
            'title' => 'Annuity Billing',
            'icon' => 'repeat',
            'description' => 'Optional recurring billing add-on for subscriptions, proration, renewals, and scheduled invoices.',
            'capabilities' => ['Subscriptions', 'Recurring invoices', 'Proration', 'Renewal reminders', 'Contract pricing'],
        ],
        'approvals-audit' => [
            'title' => 'Approvals & Audit',
            'icon' => 'shield',
            'description' => 'Approval chains and immutable audit logs for journals, invoices, POs, expense claims, and payments.',
            'capabilities' => ['Workflow rules', 'Approver chains', 'Delegation', 'Immutable audit', 'Change history', 'Period locks'],
        ],
        'ai-automation' => [
            'title' => 'AI/ML Automation',
            'icon' => 'cpu',
            'description' => 'Auto-coding recommendations, anomaly detection, cash-flow forecasting, demand forecasting, and model monitoring.',
            'capabilities' => ['Auto-coding rules', 'Forecast scenarios', 'Anomaly detection', 'Confidence scoring', 'Model drift monitoring', 'Human review queues'],
        ],
        'client-portal' => [
            'title' => 'Client Portal',
            'icon' => 'globe',
            'description' => 'Public-facing customer access for invoices, statements, payments, service requests, and document exchange.',
            'capabilities' => ['Portal access tokens', 'Invoice visibility', 'Statement downloads', 'Payment intents', 'Support requests', 'Secure document exchange'],
        ],
        'revenue-recognition' => [
            'title' => 'ASC 606 Revenue Recognition',
            'icon' => 'trending-up',
            'description' => 'Contract performance obligations, standalone selling prices, allocation, revenue schedules, and recognition journals.',
            'capabilities' => ['Revenue contracts', 'Performance obligations', 'SSP allocation', 'Recognition schedules', 'Deferred revenue', 'Disclosure support'],
        ],
        'einvoicing-tax-mesh' => [
            'title' => 'Global E-Invoicing Tax Mesh',
            'icon' => 'send',
            'description' => 'Jurisdiction profiles, tax authority connectors, clearance status, document validation, and invoice lifecycle callbacks.',
            'capabilities' => ['Country mandates', 'Authority connectors', 'Document clearance', 'Tax validation', 'Peppol/clearance routing', 'Webhook callbacks'],
        ],
        'esg-carbon' => [
            'title' => 'ESG Carbon Accounting',
            'icon' => 'activity',
            'description' => 'Scope 1, 2, and 3 emissions activity capture, emission factors, carbon-equivalent calculations, and ESG reporting.',
            'capabilities' => ['Scope classification', 'Activity data', 'Emission factors', 'CO2e calculations', 'Audit evidence', 'ESG dashboards'],
        ],
    ],

    /*
    | Which general ledger account each automatic posting uses.
    | Company Admins can override these under Accounting Settings → Account Mapping;
    | otherwise the account with the default code is used.
    */
    'account_mappings' => [
        'Cash & bank' => [
            'cash_on_hand' => ['Cash tendered', '1000'],
            'bank' => ['Bank / EFT / cheque', '1000'],
            'card' => ['Card payments', '1000'],
            'mobile_money' => ['Mobile money (EcoCash etc.)', '1000'],
        ],
        'Receivables & payables' => [
            'accounts_receivable' => ['Accounts receivable control', '1100'],
            'accounts_payable' => ['Accounts payable control', '2000'],
            'vat_output' => ['VAT on sales (output)', '2100'],
            'vat_input' => ['VAT on purchases (input)', '2100'],
            'sales_revenue' => ['Sales revenue (POS & credit notes)', '4000'],
        ],
        'Inventory' => [
            'inventory' => ['Inventory', '1200'],
            'grni' => ['Goods received not invoiced', '2050'],
            'cogs' => ['Cost of goods sold', '5000'],
            'inventory_consumption' => ['Stores issued to departments', '6000'],
        ],
        'Production & job costing' => [
            'wip' => ['Work in progress (work orders & jobs)', '1250'],
            'labour_applied' => ['Labour applied to production & jobs', '6190'],
            'overhead_applied' => ['Overhead applied to production & jobs', '6190'],
            'job_cost_of_sales' => ['Cost of sales for completed jobs', '5000'],
            'accrued_costs' => ['Accrued costs (other job costs)', '2300'],
        ],
        'Fixed assets' => [
            'fixed_assets' => ['Fixed assets at cost', '1500'],
            'accumulated_depreciation' => ['Accumulated depreciation', '1550'],
            'depreciation_expense' => ['Depreciation expense', '6500'],
        ],
        'Currency & banking' => [
            'fx_gain' => ['Exchange gains', '4200'],
            'fx_loss' => ['Exchange losses', '6600'],
            'bank_charges' => ['Bank charges', '6400'],
            'retained_earnings' => ['Retained earnings', '3000'],
            'opening_balance' => ['Opening balances (e.g. opening stock)', '3900'],
        ],
    ],
];

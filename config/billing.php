<?php

return [
    'trial_days' => 14,
    'grace_days' => 7,           // after a missed renewal before the account is suspended
    'renewal_lead_days' => 7,    // renewal invoice is issued this many days before the period ends
    'sales_email' => env('BILLING_SALES_EMAIL', 'sales@acc263.example'),

    'bank_details' => [
        'bank' => env('BILLING_BANK_NAME', 'Your bank name'),
        'account_name' => env('BILLING_BANK_ACCOUNT_NAME', 'acc263'),
        'account_number' => env('BILLING_BANK_ACCOUNT_NUMBER', '0000000000'),
        'branch' => env('BILLING_BANK_BRANCH', ''),
    ],

    // Feature keys used by plan_features. "limit" features carry a number (null = unlimited).
    'features' => [
        'users' => ['Users', 'limit'],
        'branches' => ['Branches', 'limit'],
        'companies' => ['Companies', 'limit'],
        'general_ledger' => ['General ledger', 'feature'],
        'invoicing' => ['Invoicing', 'feature'],
        'receivables_payables' => ['Accounts payable and receivable', 'feature'],
        'basic_reports' => ['Basic financial reports', 'feature'],
        'inventory' => ['Inventory management', 'feature'],
        'payroll' => ['Payroll', 'feature'],
        'bank_reconciliation' => ['Bank reconciliation', 'feature'],
        'multi_currency' => ['Multi-currency accounting', 'feature'],
        'tax_management' => ['Tax management', 'feature'],
        'advanced_reports' => ['Advanced reports', 'feature'],
        'advanced_permissions' => ['Advanced permissions', 'feature'],
        'consolidated_reporting' => ['Consolidated reporting', 'feature'],
        'audit_trail' => ['Audit trail', 'feature'],
        'priority_support' => ['Priority support', 'feature'],
        'api_access' => ['API access', 'feature'],
        'custom_integrations' => ['Custom integrations', 'feature'],
        'advanced_access_controls' => ['Advanced access controls', 'feature'],
        'dedicated_onboarding' => ['Dedicated onboarding', 'feature'],
        'dedicated_support' => ['Dedicated support arrangements', 'feature'],
    ],

    // Screens that need a feature (first match wins). Other features are listed on plans
    // but have no separate screen yet (payroll, consolidated reporting, API access, ...).
    'route_features' => [
        'accounting.banking.*' => 'bank_reconciliation',
        'accounting.settings.rates.*' => 'multi_currency',
        'accounting.gl.cash-flow' => 'advanced_reports',
        'accounting.gl.statutory.*' => 'advanced_reports',
        'accounting.reporting.*' => 'advanced_reports',
        'accounting.ar.invoices.zimra' => 'tax_management',
        'accounting.einvoicing-tax-mesh.*' => 'tax_management',
        'accounting.approvals-audit.audit.*' => 'audit_trail',
    ],
];

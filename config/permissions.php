<?php

/*
|--------------------------------------------------------------------------
| Roles & permissions
|--------------------------------------------------------------------------
|
| Single source of truth for who may do what. Enforced on every
| authenticated route by the `permission` middleware and used by the
| sidebar / buttons through the @canroute Blade directive, so the UI and
| the server can never disagree.
|
| Superusers bypass these checks.
|
*/

return [

    'roles' => [
        'admin' => 'Company Admin',
        'finance_manager' => 'Finance Manager',
        'accountant' => 'Accountant',
        'auditor' => 'Auditor',
    ],

    'abilities' => [
        // Company profile, setup wizard, currencies, tax codes, chart of accounts,
        // fiscal years, periods and team management.
        'company.manage' => ['admin'],

        // See ledger structure and settings (chart of accounts, periods, budgets, settings).
        'ledger.view' => ['admin', 'finance_manager', 'auditor'],

        // Financial statements, trial balance and statutory reports.
        'reports.view' => ['admin', 'finance_manager', 'auditor'],

        // Approve / post everything except sales invoices and customer receipts.
        'approve' => ['admin', 'finance_manager'],

        // Day-to-day work: capture and edit drafts, master data, stock movements.
        'operate' => ['admin', 'finance_manager', 'accountant'],

        // Post sales invoices and customer receipts (and POS sales) without approval.
        'sales.post' => ['admin', 'finance_manager', 'accountant'],

        // Read access to day-to-day screens.
        'view' => ['admin', 'finance_manager', 'accountant', 'auditor'],
    ],

    /*
    | Route rules, first match wins (patterns use Str::is wildcards).
    |
    |   'pattern' => 'ability'                       same ability for every request
    |   'pattern' => ['read' => ..., 'write' => ...] GET pages vs. changes
    |   'pattern' => ['module' => 'key']             use the `modules` table below
    |   'pattern' => ['module' => null]              module taken from the {module} route parameter
    |
    | "write" covers POST/PUT/PATCH/DELETE and the GET *.create / *.edit form pages.
    | A route that matches no rule is denied.
    */
    'routes' => [
        // Company setup, billing & team
        'company.pending' => 'view',
        'billing.locked' => 'view',
        'billing.*' => 'company.manage',
        'company.setup.*' => 'company.manage',
        'company.team.*' => 'company.manage',

        // Configuration & ledger structure
        'accounting.settings.rates.*' => ['read' => 'ledger.view', 'write' => 'approve'], // daily rates by finance managers
        'accounting.settings.*' => ['read' => 'ledger.view', 'write' => 'company.manage'],
        'accounting.gl.accounts.*' => ['read' => 'ledger.view', 'write' => 'company.manage'],
        'accounting.gl.fiscal-years.*' => ['read' => 'ledger.view', 'write' => 'company.manage'],
        'accounting.gl.periods.destroy' => 'approve', // closes the period
        'accounting.gl.periods.*' => ['read' => 'ledger.view', 'write' => 'company.manage'],
        'accounting.gl.budgets.*' => ['read' => 'ledger.view', 'write' => 'approve'],

        // Financial reports
        'accounting.gl.trial-balance' => 'reports.view',
        'accounting.gl.profit-and-loss' => 'reports.view',
        'accounting.gl.balance-sheet' => 'reports.view',
        'accounting.gl.cash-flow' => 'reports.view',
        'accounting.gl.statutory.*' => 'reports.view',

        // Posting that accountants may do themselves
        'accounting.ar.invoices.post' => 'sales.post',
        'accounting.ar.invoices.zimra' => 'sales.post',
        'accounting.ar.receipts.post' => 'sales.post',
        'accounting.pos.*' => ['read' => 'view', 'write' => 'sales.post'],

        // Everything else that finalises a document needs approval
        'accounting.gl.journals.post' => 'approve',
        'accounting.ar.credit-notes.post' => 'approve',
        'accounting.ap.supplier-invoices.post' => 'approve',
        'accounting.ap.payments.post' => 'approve',
        'accounting.ap.payment-runs.post' => 'approve',
        'accounting.inventory.purchase-orders.approve' => 'approve',
        'accounting.inventory.sales-orders.confirm' => 'approve',
        'accounting.inventory.stores-requisitions.approve' => 'approve',

        // Bank reconciliation: capture & matching is day-to-day; linking GL accounts, posting
        // bank-only entries and signing off a reconciliation are controls.
        'accounting.banking.accounts.*' => 'company.manage',
        'accounting.banking.lines.journal' => 'approve',
        'accounting.banking.statements.complete' => 'approve',

        // Payroll: accountants prepare runs and maintain employees; approval and paying out are controls;
        // statutory settings and PAYE tables belong to the Company Admin. Auditors read.
        'accounting.payroll.requests.*' => ['read' => 'view', 'write' => 'approve'],
        'accounting.payroll.leave-types.*' => 'approve',
        'accounting.payroll.employees.portal.*' => 'approve',
        'accounting.payroll.settings.*' => ['read' => 'ledger.view', 'write' => 'company.manage'],
        'accounting.payroll.runs.approve' => 'approve',
        'accounting.payroll.runs.pay' => 'approve',

        // Approvals & audit: everyone sees their own submissions; approvers decide; admins set the rules.
        'accounting.approvals-audit.index' => 'view',
        'accounting.approvals-audit.reject' => 'approve',
        'accounting.approvals-audit.rules.*' => ['read' => 'ledger.view', 'write' => 'company.manage'],
        'accounting.approvals-audit.audit.*' => 'ledger.view',

        // Job costing: closing a job finalises its cost into cost of sales.
        'accounting.bom-job-costing.jobs.close' => 'approve',
        'accounting.bom-job-costing.jobs.acknowledge' => 'approve',

        // Account suggestions are used by accountants while they capture supplier invoices.
        'accounting.ai-automation.suggest' => 'view',

        // Enterprise modules
        'accounting.banking.*' => ['module' => 'banking'],
        'accounting.fixed-assets.*' => ['module' => 'fixed-assets'],
        'accounting.reporting.*' => ['module' => 'reporting-analytics'],
        'accounting.crm.*' => ['module' => 'crm'],
        'accounting.bom-job-costing.*' => ['module' => 'bom-job-costing'],
        'accounting.annuity-billing.*' => ['module' => 'annuity-billing'],
        'accounting.approvals-audit.*' => ['module' => 'approvals-audit'],
        'accounting.ai-automation.*' => ['module' => 'ai-automation'],
        'accounting.client-portal.*' => ['module' => 'client-portal'],
        'accounting.revenue-recognition.*' => ['module' => 'revenue-recognition'],
        'accounting.einvoicing-tax-mesh.*' => ['module' => 'einvoicing-tax-mesh'],
        'accounting.esg-carbon.*' => ['module' => 'esg-carbon'],
        'accounting.modules.*' => ['module' => null],

        // Day-to-day defaults
        'dashboard' => 'view',
        'accounting.*' => ['read' => 'view', 'write' => 'operate'],
    ],

    'modules' => [
        // Day-to-day
        'banking' => ['read' => 'view', 'write' => 'operate'],
        'crm' => ['read' => 'view', 'write' => 'operate'],
        'bom-job-costing' => ['read' => 'view', 'write' => 'operate'],
        'annuity-billing' => ['read' => 'view', 'write' => 'operate'],
        'client-portal' => ['read' => 'view', 'write' => 'operate'],
        'einvoicing-tax-mesh' => ['read' => 'view', 'write' => 'operate'],

        // Management & control
        'fixed-assets' => ['read' => 'ledger.view', 'write' => 'approve'],
        'revenue-recognition' => ['read' => 'ledger.view', 'write' => 'approve'],
        'approvals-audit' => ['read' => 'ledger.view', 'write' => 'approve'],
        'ai-automation' => ['read' => 'ledger.view', 'write' => 'approve'],
        'esg-carbon' => ['read' => 'ledger.view', 'write' => 'approve'],
        'reporting-analytics' => ['read' => 'reports.view', 'write' => 'approve'],
    ],
];

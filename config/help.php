<?php

/*
|--------------------------------------------------------------------------
| Help Centre
|--------------------------------------------------------------------------
|
| The guides are the sections of the user manual (docs/accounting-system-user-manual.md), so the Help Centre
| and the manual can never disagree. This file only groups the sections (by their number, e.g. "10H"),
| names the screen each guide is about, and marks platform-only sections.
|
*/

return [
    'manual' => base_path('docs/accounting-system-user-manual.md'),

    'categories' => [
        'getting-started' => ['title' => 'Getting started', 'icon' => 'flag', 'intro' => 'Set up your workspace and learn how acc263 is organised.', 'sections' => ['1', '2', '3', '16', '14']],
        'accounting' => ['title' => 'Day-to-day accounting', 'icon' => 'book-open', 'intro' => 'Ledger, sales, purchases, stock, banking and assets.', 'sections' => ['4', '5', '6', '7', '8', '9', '10A', '10B']],
        'people' => ['title' => 'Payroll & people', 'icon' => 'users', 'intro' => 'Pay staff, file PAYE and NSSA, and run the employee portal.', 'sections' => ['10H', '10I']],
        'controls' => ['title' => 'Controls & insight', 'icon' => 'shield', 'intro' => 'Approvals, audit trail, reports and AI tools.', 'sections' => ['10C', '10D', '10G']],
        'operations' => ['title' => 'Operations', 'icon' => 'layers', 'intro' => 'Recurring billing, bills of materials and job costing.', 'sections' => ['10E', '10F']],
        'settings' => ['title' => 'Settings & portals', 'icon' => 'settings', 'intro' => 'Currencies, tax codes, account mapping and the client portal.', 'sections' => ['10', '11']],
        'support' => ['title' => 'Troubleshooting & reference', 'icon' => 'life-buoy', 'intro' => 'Common problems and the meaning of accounting terms.', 'sections' => ['15', '17']],
        'platform' => ['title' => 'Platform administration', 'icon' => 'server', 'intro' => 'For acc263 platform administrators.', 'sections' => ['12', '13'], 'superuser_only' => true],
    ],

    // "Start here": the order a new Company Admin should follow.
    'start_here' => ['3', '16', '10H', '14'],

    // Screen each guide describes: section => [button label, route name].
    'screens' => [
        '3' => ['Open company setup', 'company.setup.edit'],
        '4' => ['Open the dashboard', 'dashboard'],
        '5' => ['Open journals', 'accounting.gl.journals.index'],
        '6' => ['Open invoices', 'accounting.ar.invoices.index'],
        '7' => ['Open supplier invoices', 'accounting.ap.supplier-invoices.index'],
        '8' => ['Open inventory', 'accounting.inventory.overview'],
        '9' => ['Open the POS terminal', 'accounting.pos.create'],
        '10' => ['Open accounting settings', 'accounting.settings.index'],
        '10A' => ['Open bank reconciliation', 'accounting.banking.index'],
        '10B' => ['Open depreciation runs', 'accounting.fixed-assets.depreciation.index'],
        '10C' => ['Open approvals', 'accounting.approvals-audit.index'],
        '10D' => ['Open reporting', 'accounting.reporting.index'],
        '10E' => ['Open annuity billing', 'accounting.annuity-billing.index'],
        '10F' => ['Open BOM & job costing', 'accounting.bom-job-costing.index'],
        '10G' => ['Open AI automation', 'accounting.ai-automation.index'],
        '10H' => ['Open payroll', 'accounting.payroll.index'],
        '10I' => ['Open leave & overtime', 'accounting.payroll.requests.index'],
        '11' => ['Open customers', 'accounting.ar.customers.index'],
        '12' => ['Open the platform dashboard', 'system.platform.dashboard'],
    ],

    // Files offered for download besides the PDFs: key => [title, description, path under resources/help].
    'templates' => [
        'bank-statement' => ['Bank statement import (CSV)', 'Columns acc263 recognises when you import a bank statement for reconciliation.', 'templates/bank-statement-template.csv'],
    ],
];

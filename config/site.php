<?php

/*
| Public website content. Contact details come from .env and are only shown when set.
*/
return [
    'email' => env('SITE_CONTACT_EMAIL', env('BILLING_SALES_EMAIL', 'sales@acc263.example')),
    'phone' => env('SITE_CONTACT_PHONE'),
    'whatsapp' => env('SITE_CONTACT_WHATSAPP'),
    'address' => env('SITE_CONTACT_ADDRESS'),

    'modules' => [
        'ledger' => ['General ledger', 'book-open', 'Double-entry books that stay correct.', [
            'Chart of accounts with a ready-made standard set',
            'Fiscal years and monthly periods; closed periods cannot be posted to',
            'Manual journals with approval before posting',
            'Posted entries are permanent: corrections are made by reversal',
            'Budgets by account and period',
        ]],
        'receivables' => ['Invoicing & receivables', 'file-text', 'Bill customers and get paid.', [
            'Sales invoices with VAT and PDF download',
            'Customer receipts matched to invoices',
            'Credit notes, aging and dunning',
            'Client portal where customers view invoices and report payments',
            'Submit invoices to ZIMRA FDMS',
        ]],
        'payables' => ['Purchases & payables', 'card', 'Control what you owe and pay on time.', [
            'Supplier invoices posted after approval',
            'Supplier payments and batch payment runs',
            'Payables aging',
            'Goods received not invoiced tracked automatically',
        ]],
        'inventory' => ['Inventory & orders', 'package', 'Stock that agrees with your books.', [
            'Items, warehouses and branches',
            'Purchase orders, sales orders, transfers and stores requisitions',
            'Weighted-average or standard costing',
            'Every stock movement posts to the ledger; a check shows stock and ledger agree',
            'Reorder levels and low-stock warnings',
        ]],
        'pos' => ['Point of sale', 'cart', 'Sell over the counter.', [
            'Fast POS terminal with live stock per branch',
            'Cash, card, mobile money (EcoCash etc.) and EFT tenders',
            'Receipts, cost of sales and VAT posted instantly',
        ]],
        'banking' => ['Banking & reconciliation', 'layers', 'Know your bank balance is right.', [
            'Import bank statements (CSV)',
            'Automatic matching to ledger entries',
            'Post bank charges and interest in one click',
            'Sign-off when the statement and books agree',
        ]],
        'currency' => ['Multi-currency', 'globe', 'Trade in USD, ZiG, Rand and more.', [
            'Choose a base currency; invoice and pay in others',
            'Exchange rates by date',
            'Realised exchange gains and losses posted automatically',
        ]],
        'tax' => ['Tax & statutory', 'check', 'Stay compliant.', [
            'VAT codes for standard, zero-rated and exempt supplies',
            'ZIMRA TIN and NSSA details captured at setup',
            'Statutory report pack in Excel: trial balance, P&L, balance sheet, VAT, aging',
        ]],
        'assets' => ['Fixed assets', 'briefcase', 'Depreciation without spreadsheets.', [
            'Asset register with cost, net book value and status',
            'Monthly depreciation runs: straight line or declining balance',
            'Posted to the ledger automatically',
        ]],
        'reports' => ['Financial reports', 'bar-chart', 'See where the business stands.', [
            'Trial balance, profit & loss, balance sheet and cash flow',
            'Balance sheet that always balances, with current-year profit',
            'Reports by period or fiscal year to date',
        ]],
        'team' => ['Team, roles & approvals', 'users', 'The right access for each person.', [
            'Company Admin, Finance Manager, Accountant and Auditor roles',
            'Accountants post invoices and receipts; everything else goes for approval',
            'Read-only access for auditors',
            'Each business’s data is kept separate',
        ]],
        'more' => ['And more', 'repeat', 'Tools that grow with you.', [
            'CRM opportunities, BOM & job costing, subscription (annuity) billing',
            'Revenue recognition, e-invoicing and ESG carbon tracking',
            'Windows desktop app',
        ]],
    ],
];

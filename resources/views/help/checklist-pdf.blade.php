<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>acc263 setup checklist</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.5; color: #1b2433; }
        .brand { color: #1957c2; font-weight: bold; font-size: 16px; }
        h1 { font-size: 22px; margin: 6px 0 2px; }
        .intro { color: #5b6677; margin-bottom: 14px; }
        h2 { font-size: 12.5px; color: #fff; background: #1957c2; padding: 5px 8px; margin: 16px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 4px; border-bottom: 1px solid #e3e8ef; vertical-align: top; }
        .box { width: 16px; } .box span { display: inline-block; width: 11px; height: 11px; border: 1.2px solid #1b2433; margin-top: 1px; }
        .where { color: #5b6677; font-size: 9.5px; width: 34%; }
        .note { margin-top: 16px; padding: 8px 10px; background: #fff7e0; border: 1px solid #f0d58a; font-size: 9.5px; }
    </style>
</head>
<body>
@php
    $groups = [
        '1. Company setup (Company Admin)' => [
            ['Company profile: name, country, VAT number, registration number and at least one bank account', 'Setup wizard step 1 · Company Profile', '3.1'],
            ['Base currency and the other currencies you trade in', 'Setup wizard step 2', '3.2'],
            ['ZIMRA TIN, NSSA employer number and the standard VAT rate', 'Setup wizard step 3', '3.3'],
            ['First month of the financial year (creates periods and the chart of accounts)', 'Setup wizard step 4', '3.4'],
            ['Exchange rates for each foreign currency (keep them up to date)', 'Accounting Settings → Exchange rates', '10.2'],
            ['Review tax codes and Account Mapping (which GL account each automatic posting uses)', 'Accounting Settings', '10.3–10.4'],
        ],
        '2. People and controls' => [
            ['Add team members and give each a role (Admin, Finance Manager, Accountant, Auditor)', 'Company → Team', '3.5–3.6'],
            ['Optional approval rules: amount thresholds and "preparer cannot approve"', 'Approvals & Audit → Approval rules', '10C'],
            ['Choose a plan before the 14-day trial ends', 'Company → Billing & Plan', '3.7'],
        ],
        '3. Master data and opening balances' => [
            ['Customers (with credit limits and currency) and suppliers', 'Accounts Receivable / Payable', '6.1, 7.1'],
            ['Warehouses, then items with opening quantities and costs', 'Inventory → Warehouses, Items', '8.2–8.3'],
            ['Opening balances by journal against Opening Balance Equity (3900)', 'General Ledger → Journals', '5.4'],
            ['Link each bank account to its GL account for reconciliation', 'Bank Reconciliation', '10A'],
            ['Fixed assets with cost, useful life and depreciation method', 'Fixed Assets', '10B'],
        ],
        '4. Payroll (if you use it)' => [
            ['Statutory settings for the tax year: load the published ZIMRA/NSSA figures, review and save', 'Payroll → Statutory settings', '10H'],
            ['Pay items: allowances, pension (before tax), medical aid, loans, and an hourly Overtime item', 'Payroll → Pay items', '10H'],
            ['Employees: TIN, NSSA number, salary, currency split, payment details, tax credits', 'Payroll → Employees', '10H'],
            ['Leave types: set the yearly entitlement for each type you track', 'Payroll → Leave & overtime', '10I'],
            ['Employee portal access: issue one-time passwords (employees need a work email)', 'Employee page → Employee portal', '10I'],
        ],
        '5. Before you go live' => [
            ['Post a test invoice and receipt, and check the trial balance', 'Accounts Receivable · Reports', '14.2'],
            ['Run a trial payroll and check payslips before approving', 'Payroll → Payroll runs', '10H'],
            ['Make sure the scheduler is running (renewals, recurring billing, AI scans)', 'Server: php artisan schedule:work', '10G'],
            ['Share the portal addresses: /staff/login for employees, /client/login for customers', '—', '10I, 11'],
        ],
    ];
@endphp
    <div class="brand">acc263</div>
    <h1>Setup checklist</h1>
    <div class="intro">Work through these in order. The section numbers refer to the user manual and the Help Centre (Help in the top bar).</div>

    @foreach($groups as $group => $items)
        <h2>{{ $group }}</h2>
        <table>
            @foreach($items as [$task, $where, $section])
                <tr><td class="box"><span></span></td><td>{{ $task }}</td><td class="where">{{ $where }}<br>Guide {{ $section }}</td></tr>
            @endforeach
        </table>
    @endforeach

    <div class="note"><strong>Tax figures:</strong> acc263 offers the ZIMRA and NSSA figures it was built with, but tax tables change. Check them against zimra.co.zw and nssa.org.zw, or with your tax adviser, before running payroll.</div>
</body>
</html>

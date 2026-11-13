<?php

/*
|--------------------------------------------------------------------------
| Published PAYE and NSSA figures
|--------------------------------------------------------------------------
|
| Offered to Company Admins on Payroll → Statutory settings ("Load published figures"); nothing is applied
| until an admin saves it. Each set records where the figures come from. Add a new set when ZIMRA or NSSA
| publish new figures.
|
| PAYE: ZIMRA "Pay As You Earn (PAYE) Foreign Currency Tax Tables for January to December 2025" (USD) and
| "PAYE ZWG Tax Tables for 1 January to 31 December 2025", monthly tables, read from the PDFs on
| https://www.zimra.co.zw/domestic-taxes/tax-tables (retrieved 13 Nov 2026). ZIMRA's tax-tables page listed
| no 2026 tables at that date.
| Credits, medical credit, AIDS levy, mixed salaries: https://www.zimra.co.zw/domestic-taxes/individual/pay-as-you-earn-paye
|   "tax credits e.g. elderly, blind or disabled persons (US$900.00 or ZWG equivalent per annum)",
|   "medical credit $1.00 of every $2.00 paid", "Calculate 3% Aids Levy and add to tax after credits",
|   "for salaries with both ZWG and USD components use the USD tax tables and apportion the tax due accordingly".
| NSSA: 4.5% employee + 4.5% employer on insurable earnings up to USD 700 a month; for ZiG payrolls the
|   ceiling is converted at the interbank mid-rate (NSSA contributions page, https://www.nssa.org.zw/contributions/).
| NSSA deductible: widely reported as reducing taxable income; not confirmed from the Income Tax Act text.
|
*/

$credits = [
    ['code' => 'elderly', 'name' => 'Elderly person credit', 'amount' => 75.00, 'currency' => 'USD'],   // US$900 a year
    ['code' => 'blind', 'name' => 'Blind person credit', 'amount' => 75.00, 'currency' => 'USD'],
    ['code' => 'disabled', 'name' => 'Disabled person credit', 'amount' => 75.00, 'currency' => 'USD'],
];

$common = [
    'nssa_employee_rate' => 4.5,
    'nssa_employer_rate' => 4.5,
    'nssa_ceiling' => 700.00,
    'nssa_ceiling_currency' => 'USD', // converted at the pay-date rate for ZWG payrolls
    'nssa_deductible' => true,
    'aids_levy_rate' => 3.0,
    'medical_aid_credit_rate' => 50.0,
    'credits' => $credits,
    'levies' => [],
];

return [
    'sets' => [
        '2025' => [
            'label' => 'ZIMRA PAYE tables 1 Jan – 31 Dec 2025 and NSSA rates',
            'source' => 'ZIMRA USD and ZWG 2025 PAYE tax tables; ZIMRA PAYE guidance; NSSA contributions page',
            'currencies' => [
                'USD' => $common + ['bands' => [
                    ['lower' => 0, 'upper' => 100.00, 'rate' => 0, 'deduct' => 0],
                    ['lower' => 100.01, 'upper' => 300.00, 'rate' => 20, 'deduct' => 20.00],
                    ['lower' => 300.01, 'upper' => 1000.00, 'rate' => 25, 'deduct' => 35.00],
                    ['lower' => 1000.01, 'upper' => 2000.00, 'rate' => 30, 'deduct' => 85.00],
                    ['lower' => 2000.01, 'upper' => 3000.00, 'rate' => 35, 'deduct' => 185.00],
                    ['lower' => 3000.01, 'upper' => null, 'rate' => 40, 'deduct' => 335.00],
                ]],
                'ZWG' => $common + ['bands' => [
                    ['lower' => 0, 'upper' => 2800.00, 'rate' => 0, 'deduct' => 0],
                    ['lower' => 2800.01, 'upper' => 8400.00, 'rate' => 20, 'deduct' => 560.00],
                    ['lower' => 8400.01, 'upper' => 28000.00, 'rate' => 25, 'deduct' => 980.00],
                    ['lower' => 28000.01, 'upper' => 56000.00, 'rate' => 30, 'deduct' => 2380.00],
                    ['lower' => 56000.01, 'upper' => 84000.00, 'rate' => 35, 'deduct' => 5180.00],
                    ['lower' => 84000.01, 'upper' => null, 'rate' => 40, 'deduct' => 9380.00],
                ]],
            ],
        ],
    ],

    // Tax year => published set to offer. ZIMRA had not published 2026 tables when this was written, so 2026
    // offers the 2025 tables and says so; replace when 2026 tables are published.
    'years' => [
        2025 => '2025',
        2026 => '2025',
    ],
];

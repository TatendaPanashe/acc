<?php

/*
| ZIMRA FDMS fiscalisation through the `zimra-fdms` npm package (node_modules/zimra-fdms).
| Each company has its own fiscal device, set up by a superuser under System → Fiscalisation;
| its certificate, private key and fiscal-day state are kept in profiles_path/{company id}.
*/
return [
    'node_binary' => env('ZIMRA_NODE_BINARY', 'node'),
    'cli' => env('ZIMRA_CLI', base_path('node_modules/zimra-fdms/dist/node/cli.js')),
    'profiles_path' => env('ZIMRA_PROFILES_PATH', storage_path('app/private/zimra')),
    'timeout' => (int) env('ZIMRA_TIMEOUT', 90),

    // Point at the local simulator (npx zimra-fdms-simulator) instead of ZIMRA, for development only.
    'base_url' => env('ZIMRA_BASE_URL'),
    'ca' => env('ZIMRA_CA'),

    'environments' => [
        'test' => 'Test (fdmsapitest.zimra.co.zw)',
        'production' => 'Production (live fiscal invoices)',
    ],
    'payment_types' => ['Credit', 'Cash', 'Card', 'MobileWallet', 'BankTransfer', 'Coupon', 'Other'],
];

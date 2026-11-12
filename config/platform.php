<?php

return [
    'name' => 'acc263',

    // Plan new registrations start their trial on.
    'default_plan' => 'professional',

    'payment_providers' => [
        'pesepay' => 'Pesepay',
        'paynow' => 'Paynow',
        'ecocash' => 'EcoCash',
        'innbucks' => 'InnBucks',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Bank transfer',
        'sandbox' => 'Sandbox (test)',
        'coupon' => 'Coupon (no payment)',
    ],

    'countries' => [
        'ZW' => 'Zimbabwe', 'ZA' => 'South Africa', 'BW' => 'Botswana', 'ZM' => 'Zambia', 'MZ' => 'Mozambique',
        'MW' => 'Malawi', 'NA' => 'Namibia', 'LS' => 'Lesotho', 'SZ' => 'Eswatini', 'AO' => 'Angola',
        'TZ' => 'Tanzania', 'KE' => 'Kenya', 'UG' => 'Uganda', 'RW' => 'Rwanda', 'ET' => 'Ethiopia',
        'NG' => 'Nigeria', 'GH' => 'Ghana', 'CD' => 'DR Congo', 'EG' => 'Egypt', 'MU' => 'Mauritius',
        'GB' => 'United Kingdom', 'IE' => 'Ireland', 'US' => 'United States', 'CA' => 'Canada', 'AU' => 'Australia',
        'NZ' => 'New Zealand', 'AE' => 'United Arab Emirates', 'IN' => 'India', 'CN' => 'China', 'DE' => 'Germany',
        'FR' => 'France', 'NL' => 'Netherlands', 'PT' => 'Portugal',
    ],
];

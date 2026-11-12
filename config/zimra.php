<?php

return [
    'profile' => env('ZIMRA_FDMS_PROFILE', base_path('.zimra')),
    'tax_id' => (int) env('ZIMRA_FDMS_TAX_ID', 1),
    'exempt_tax_id' => (int) env('ZIMRA_FDMS_EXEMPT_TAX_ID', 1),
    'payment_type' => env('ZIMRA_FDMS_PAYMENT_TYPE', 'Credit'),
    'lines_tax_inclusive' => env('ZIMRA_FDMS_LINES_TAX_INCLUSIVE', false),
];

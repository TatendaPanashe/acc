<?php

namespace App\Modules\Accounting\Models;

class Customer extends AccountingModel
{
    protected $table = 'accounting_customers';

    protected $fillable = [
        'company_id', 'customer_number', 'name', 'email', 'phone',
        'portal_password', 'portal_password_changed_at', 'portal_last_login_at',
        'billing_address', 'credit_limit', 'currency', 'status',
    ];

    protected $hidden = ['portal_password'];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'portal_password_changed_at' => 'datetime',
        'portal_last_login_at' => 'datetime',
        'status' => 'boolean',
    ];
}

<?php

namespace App\Modules\Accounting\Models;

class PortalAccess extends AccountingModel
{
    protected $table = 'accounting_portal_accesses';

    protected $fillable = ['company_id', 'customer_id', 'portal_token', 'contact_name', 'email', 'password', 'permissions', 'expires_at', 'last_login_at', 'status'];

    protected $hidden = ['password'];

    protected $casts = ['permissions' => 'array', 'expires_at' => 'datetime', 'last_login_at' => 'datetime', 'status' => 'boolean'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

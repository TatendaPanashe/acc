<?php

namespace App\Models;

class CompanyBankAccount extends TenantModel
{
    protected $fillable = [
        'company_id',
        'gl_account_id',
        'bank_name',
        'account_name',
        'account_number',
        'branch_code',
        'swift_code',
        'currency',
        'is_primary',
        'status',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'status' => 'boolean',
    ];

    public function glAccount()
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Account::class, 'gl_account_id');
    }

    public function label(): string
    {
        return $this->bank_name.' '.$this->account_number.' ('.$this->currency.')';
    }
}

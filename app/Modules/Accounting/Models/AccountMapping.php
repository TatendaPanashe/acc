<?php

namespace App\Modules\Accounting\Models;

class AccountMapping extends AccountingModel
{
    protected $table = 'accounting_account_mappings';

    protected $fillable = ['company_id', 'key', 'account_id'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}

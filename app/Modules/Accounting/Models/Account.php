<?php

namespace App\Modules\Accounting\Models;

class Account extends AccountingModel
{
    protected $table = 'accounting_accounts';

    protected $fillable = [
        'company_id', 'parent_id', 'code', 'name', 'type', 'normal_balance',
        'is_control_account', 'allow_posting', 'status',
    ];

    protected $casts = [
        'is_control_account' => 'boolean',
        'allow_posting' => 'boolean',
        'status' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }
}

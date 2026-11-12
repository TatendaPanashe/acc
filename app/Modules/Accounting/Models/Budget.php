<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends AccountingModel
{
    protected $table = 'accounting_budgets';

    protected $fillable = ['company_id', 'accounting_period_id', 'account_id', 'currency', 'amount', 'notes'];

    protected $casts = ['amount' => 'decimal:2'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }
}

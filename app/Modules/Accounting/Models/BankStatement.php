<?php

namespace App\Modules\Accounting\Models;

use App\Models\CompanyBankAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends AccountingModel
{
    protected $table = 'accounting_bank_statements';

    protected $fillable = [
        'company_id', 'company_bank_account_id', 'bank_account_name', 'format', 'statement_date',
        'opening_balance', 'closing_balance', 'file_path', 'status', 'reconciled_at', 'reconciled_by',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->orderBy('transaction_date')->orderBy('id');
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }
}

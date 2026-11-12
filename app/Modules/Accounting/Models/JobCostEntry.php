<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class JobCostEntry extends AccountingModel
{
    protected $table = 'accounting_job_cost_entries';

    protected $fillable = [
        'company_id', 'job_cost_id', 'entry_date', 'category', 'description', 'quantity', 'unit_cost', 'amount',
        'item_id', 'warehouse_id', 'credit_account_id', 'journal_entry_id', 'created_by',
    ];

    protected $casts = ['entry_date' => 'date', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'amount' => 'decimal:2'];

    public function item() { return $this->belongsTo(Item::class); }
    public function creditAccount() { return $this->belongsTo(Account::class, 'credit_account_id'); }
    public function journalEntry() { return $this->belongsTo(JournalEntry::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

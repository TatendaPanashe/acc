<?php

namespace App\Modules\Accounting\Models;

class BankStatementLine extends AccountingModel
{
    protected $table = 'accounting_bank_statement_lines';

    protected $fillable = ['company_id', 'bank_statement_id', 'transaction_date', 'description', 'reference', 'amount', 'journal_line_id'];

    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:2'];

    public function statement()
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function journalLine()
    {
        return $this->belongsTo(JournalLine::class);
    }
}

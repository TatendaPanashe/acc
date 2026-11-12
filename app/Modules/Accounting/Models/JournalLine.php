<?php

namespace App\Modules\Accounting\Models;

use LogicException;

class JournalLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_journal_lines';

    protected $fillable = [
        'company_id', 'journal_entry_id', 'account_id', 'description',
        'debit', 'credit', 'fx_debit', 'fx_credit', 'tax_code',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'fx_debit' => 'decimal:2',
        'fx_credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();

        $guard = function (self $line) {
            $entry = JournalEntry::withoutGlobalScopes()->find($line->journal_entry_id);
            if ($entry?->isPosted()) {
                throw new LogicException('Lines of posted journal entry '.$entry->entry_number.' cannot be changed.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

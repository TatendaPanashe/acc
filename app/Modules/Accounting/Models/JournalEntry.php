<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class JournalEntry extends AccountingModel
{
    protected $table = 'accounting_journal_entries';

    protected $fillable = [
        'company_id', 'accounting_period_id', 'entry_number', 'entry_date',
        'source_module', 'source_type', 'source_id', 'currency', 'exchange_rate',
        'memo', 'status', 'created_by', 'posted_by', 'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'exchange_rate' => 'decimal:8',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // Posted entries are permanent: correct them with a reversing entry instead.
        static::updating(function (self $entry) {
            if ($entry->getOriginal('status') === 'posted') {
                throw new LogicException('Posted journal entry '.$entry->entry_number.' cannot be changed.');
            }
        });

        static::deleting(function (self $entry) {
            if ($entry->getOriginal('status') === 'posted') {
                throw new LogicException('Posted journal entry '.$entry->entry_number.' cannot be deleted.');
            }
        });
    }

    public function isPosted(): bool
    {
        return $this->getOriginal('status') === 'posted';
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}

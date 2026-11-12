<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

/** A finding from anomaly detection, for a person to confirm or dismiss. */
class AiInsight extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_ai_insights';

    public const TYPES = [
        'unusual_amount' => 'Unusual amount for the account',
        'duplicate_supplier_invoice' => 'Possible duplicate supplier invoice',
        'round_amount_journal' => 'Large round-number manual journal',
        'weekend_posting' => 'Manual journal posted at the weekend',
        'self_posted_journal' => 'Journal posted by the person who prepared it',
        'benford_deviation' => 'Supplier invoice amounts deviate from Benford\'s law',
    ];

    protected $fillable = ['company_id', 'type', 'severity', 'score', 'title', 'subject_type', 'subject_id', 'subject_label', 'amount', 'details', 'fingerprint', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected $casts = ['score' => 'decimal:2', 'amount' => 'decimal:2', 'details' => 'array', 'reviewed_at' => 'datetime'];

    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}

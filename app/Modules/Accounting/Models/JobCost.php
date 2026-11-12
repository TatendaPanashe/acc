<?php

namespace App\Modules\Accounting\Models;

/** A project / job whose costs accumulate in WIP until the job is closed to cost of sales. */
class JobCost extends AccountingModel
{
    protected $table = 'accounting_job_costs';

    public const STATUSES = ['planned' => 'Planned', 'wip' => 'In progress', 'variance_review' => 'Over budget: review', 'completed' => 'Completed', 'closed' => 'Closed'];
    public const CATEGORIES = ['materials' => 'Materials (from stock)', 'labour' => 'Labour', 'overhead' => 'Overhead', 'subcontract' => 'Subcontractors', 'other' => 'Other costs'];

    protected $fillable = [
        'company_id', 'customer_id', 'job_number', 'name', 'type', 'estimated_cost', 'actual_cost', 'due_on', 'status', 'notes',
        'budget_materials', 'budget_labour', 'budget_overhead', 'budget_other', 'contract_value', 'billing_method', 'markup_percent',
        'invoiced_amount', 'variance_tolerance', 'started_on', 'closed_on', 'closing_entry_id',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2', 'due_on' => 'date', 'started_on' => 'date', 'closed_on' => 'date',
        'budget_materials' => 'decimal:2', 'budget_labour' => 'decimal:2', 'budget_overhead' => 'decimal:2', 'budget_other' => 'decimal:2',
        'contract_value' => 'decimal:2', 'markup_percent' => 'decimal:2', 'invoiced_amount' => 'decimal:2', 'variance_tolerance' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function entries() { return $this->hasMany(JobCostEntry::class)->latest('entry_date')->latest('id'); }
    public function invoices() { return $this->hasMany(SalesInvoice::class); }

    /** Budget per category (subcontract and other share the "other" budget). */
    public function budgetFor(string $category): float
    {
        return (float) match ($category) {
            'materials' => $this->budget_materials,
            'labour' => $this->budget_labour,
            'overhead' => $this->budget_overhead,
            default => $this->budget_other,
        };
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, ['closed'], true);
    }
}

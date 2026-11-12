<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class BillingRun extends AccountingModel
{
    protected $table = 'accounting_billing_runs';

    protected $fillable = ['company_id', 'run_number', 'run_date', 'invoice_count', 'total', 'posted', 'created_by'];

    protected $casts = ['run_date' => 'date', 'total' => 'decimal:2', 'posted' => 'boolean'];

    public function invoices() { return $this->hasMany(SalesInvoice::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

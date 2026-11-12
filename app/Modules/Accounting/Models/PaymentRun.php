<?php

namespace App\Modules\Accounting\Models;

class PaymentRun extends AccountingModel
{
    protected $table = 'accounting_payment_runs';

    protected $fillable = ['company_id', 'run_number', 'payment_date', 'currency', 'payment_method', 'total_amount', 'status'];

    protected $casts = ['payment_date' => 'date', 'total_amount' => 'decimal:2'];

    public function payments() { return $this->hasMany(SupplierPayment::class, 'payment_run_id'); }
}

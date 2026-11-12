<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class PosSale extends AccountingModel
{
    protected $table = 'accounting_pos_sales';

    protected $fillable = [
        'company_id', 'customer_id', 'warehouse_id', 'journal_entry_id', 'cashier_id',
        'receipt_number', 'sold_at', 'currency', 'payment_method', 'subtotal',
        'tax_total', 'discount_total', 'total', 'amount_tendered', 'change_due', 'status',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_due' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function cashier() { return $this->belongsTo(User::class, 'cashier_id'); }
    public function lines() { return $this->hasMany(PosSaleLine::class); }
    public function journalEntry() { return $this->belongsTo(JournalEntry::class); }
}

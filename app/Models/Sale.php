<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sale_number',
        'sale_date',
        'customer_id',
        'total_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status', // e.g., 'completed', 'pending', 'cancelled'
        'user_id', // Employee who made the sale
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'sale_date'   => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'change_amount' => 'decimal:2',
        'customer_id'  => 'integer',
        'user_id'      => 'integer',
    ];

    /**
     * Get the customer that made the sale.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user (employee) who recorded the sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id'); // Link to Employee model
    }

    /**
     * Get the items for the sale.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Generate a unique sale number.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($sale) {
            $sale->sale_number = 'SALE-' . date('YmdHis') . rand(1000, 9999);
        });
    }
}

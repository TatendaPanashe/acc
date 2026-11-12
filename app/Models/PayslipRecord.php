<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipRecord extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', // Corrected from 'id' to 'user_id'
        'pay_period_start',
        'pay_period_end',
        'pay_date',
        'gross_earnings',
        'total_deductions',
        'net_pay',
        'ytd_gross_earnings',
        'ytd_total_deductions',
        'ytd_net_pay',
        'ytd_tax_paid',
        'pdf_path', // Path to the generated PDF file if stored
        'currency',
        'data_snapshot', // Store a JSON snapshot of all payslip data for immutable record
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'pay_period_start'     => 'date',
        'pay_period_end'       => 'date',
        'pay_date'             => 'date',
        'gross_earnings'       => 'decimal:2',
        'total_deductions'     => 'decimal:2',
        'net_pay'              => 'decimal:2',
        'ytd_gross_earnings'   => 'decimal:2',
        'ytd_total_deductions' => 'decimal:2',
        'ytd_net_pay'          => 'decimal:2',
        'ytd_tax_paid'         => 'decimal:2',
        'data_snapshot'        => 'array', // Cast JSON to array
    ];

    /**
     * Get the user associated with this payslip record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id'); // Link to Employee model
    }
}

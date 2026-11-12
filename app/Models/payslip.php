<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class payslip extends Model
{
    /** @use HasFactory<\Database\Factories\PayslipFactory> */
    use HasFactory;
     protected $table = 'payslips';
    protected $fillable = [
        'employee_id',
        'pay_period_start',
        'pay_period_end',
        'basic_salary',
        'leave_days_accrued',
        'total_deductions',
        'net_pay',
    ];


    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

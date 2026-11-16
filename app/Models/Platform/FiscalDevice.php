<?php

namespace App\Models\Platform;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/** A company's ZIMRA FDMS fiscal device (platform-managed, not tenant-scoped). */
class FiscalDevice extends Model
{
    protected $fillable = [
        'company_id', 'enabled', 'environment', 'device_id', 'serial_number', 'model_name', 'model_version', 'activation_key',
        'standard_tax_id', 'zero_rated_tax_id', 'exempt_tax_id', 'zero_rate_lines_as', 'payment_type', 'lines_tax_inclusive',
        'auto_submit', 'auto_close_day', 'registered_at', 'taxpayer_name', 'taxpayer_tin', 'vat_number', 'branch_name',
        'certificate_valid_till', 'applicable_taxes', 'fiscal_day_status', 'fiscal_day_no', 'last_receipt_global_no',
        'last_checked_at', 'last_error',
    ];

    protected $hidden = ['activation_key'];

    protected $casts = [
        'enabled' => 'boolean',
        'activation_key' => 'encrypted',
        'lines_tax_inclusive' => 'boolean',
        'auto_submit' => 'boolean',
        'auto_close_day' => 'boolean',
        'registered_at' => 'datetime',
        'certificate_valid_till' => 'datetime',
        'applicable_taxes' => 'array',
        'last_checked_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isRegistered(): bool
    {
        return $this->registered_at !== null;
    }

    /** Enabled and registered: invoices can be fiscalised. */
    public function isActive(): bool
    {
        return $this->enabled && $this->isRegistered();
    }

    public function dayIsOpen(): bool
    {
        return $this->fiscal_day_status === 'FiscalDayOpened';
    }

    public function statusLabel(): string
    {
        return match (true) {
            !$this->isRegistered() => 'Not registered',
            !$this->enabled => 'Registered, disabled',
            $this->last_error !== null => 'Needs attention',
            default => 'Active',
        };
    }

    public function statusBadge(): string
    {
        return match ($this->statusLabel()) {
            'Active' => 'active',
            'Needs attention' => 'failed',
            'Registered, disabled' => 'pending',
            default => 'cancelled',
        };
    }
}

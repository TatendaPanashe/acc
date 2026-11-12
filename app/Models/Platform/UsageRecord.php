<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    protected $table = 'platform_usage_records';

    protected $fillable = ['company_id', 'metric', 'quantity', 'period'];
}

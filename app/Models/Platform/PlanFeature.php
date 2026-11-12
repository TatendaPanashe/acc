<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    protected $table = 'platform_plan_features';

    protected $fillable = ['platform_plan_id', 'feature_key', 'limit_value'];
}

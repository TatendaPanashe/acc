<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\belongsTo;

class Tax extends Model
{
    //
    protected $fillable = [
        'tax_year_id',
        'name',
        'min',
        'maximum',
        'percentage',
        'addition',
        'user_id',
        'date_created',
        'date_updated',
    ];

    public $timestamps = true;


    public function taxYear() : BelongsTo
    {
        return $this->belongsTo(TaxYear::class);
    }

     public function getActivetax() : BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

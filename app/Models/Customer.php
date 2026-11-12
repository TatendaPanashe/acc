<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'phone', 'address'];

    /**
     * Get the sales made to the customer.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}

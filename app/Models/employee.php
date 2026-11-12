<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    protected $table = 'employees';
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'telephone',
        'designation',
        'date_of_birth',
        'date_joined',
        'address',
        'city',
        'state',
        'country',
        'gender',
    ];

/**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the grade associated with the user.
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
    public function id(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }


    /**
     * Get the earnings for the user.
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /**
     * Get the deductions for the user.
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(Deduction::class);
    }

    /**
     * Get the leaves for the user.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function getActiveTax(){

        return $this->hasMany(TaxYear::class);
    }

}

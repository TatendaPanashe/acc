<?php

namespace App\Models;

use App\Notifications\Account\VerifyEmailAddress;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'company_id',
        'email',
        'google_id',
        'role',
        'is_superuser',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superuser' => 'boolean',
        ];
    }

    /** Verification link in the acc263 email layout (sent at sign-up and from "resend"). */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailAddress);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_superuser || $this->role === 'admin';
    }

    public function roleLabel(): string
    {
        return $this->is_superuser ? 'Superuser' : (config('permissions.roles.'.$this->role) ?? ucwords(str_replace('_', ' ', (string) $this->role)));
    }

}

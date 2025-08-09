<?php
namespace App\Models;

use Laravel\Sanctum\HasApiTokens; // ✅ ADD THIS
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Seller extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // ✅ UPDATE THIS LINE

    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'role',
        'verification_code',
        'verified',
        'profile_updated',
        'is_professional',
        'status',
        'is_subscribed',
        'subscription_type',
        'subscription_expires_at',
        'email_verified_at',
        'password_reset_code',
        'password_reset_sent_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    protected $casts = [
        'verified'                => 'boolean',
        'profile_updated'         => 'boolean',
        'is_professional'         => 'boolean',
        'status'                  => 'boolean',
        'is_subscribed'           => 'boolean',
        'subscription_expires_at' => 'datetime',
        'email_verified_at',
        'password_reset_sent_at',
    ];
}

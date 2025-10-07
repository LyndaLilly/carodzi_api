<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Buyer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'role',
        'verification_code',
        'verified',
        'profile_updated',
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
        'verified'               => 'boolean',
        'profile_updated'        => 'boolean',
        'email_verified_at'      => 'datetime',
        'password_reset_sent_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->hasOne(BuyerProfile::class, 'buyer_id');
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'buyer_id');
    }

    public function wishs()
    {
        return $this->hasMany(Wish::class, 'buyer_id');
    }

}

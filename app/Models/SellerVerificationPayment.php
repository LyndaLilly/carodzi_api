<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerVerificationPayment extends Model
{
    protected $fillable = [
        'seller_id',
        'reference',
        'amount',
        'status',
        'starts_at',
        'ends_at',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'expires_at' => 'datetime',
        'paid_at'    => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(\App\Models\Seller::class, 'seller_id');
    }
}

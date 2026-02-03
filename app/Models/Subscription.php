<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'seller_id',
        'plan',
        'starts_at',
        'expires_at',
        'is_active',
        'paystack_reference', // add reference
    ];

    protected $casts = [
        'starts_at' => 'date',
        'expires_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function isValid()
    {
        return $this->is_active && $this->expires_at->isFuture();
    }
}


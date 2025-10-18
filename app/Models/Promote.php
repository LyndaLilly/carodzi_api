<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Promote extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'plan',
        'duration',
        'start_date',
        'end_date',
        'is_active',
        'is_approved',
        'payment_method',
        'transaction_reference',
        'crypto_hash',
        'amount',
        'approved_at',
        'expired_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'approved_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    /**
     * Relationship: each promotion belongs to a seller
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Check if the promotion has expired
     */
    public function getIsExpiredAttribute()
    {
        return $this->end_date && Carbon::now()->greaterThan($this->end_date);
    }

    /**
     * Automatically deactivate expired promotions when fetched
     */
    protected static function booted()
    {
        static::retrieved(function ($promotion) {
            if ($promotion->is_active && $promotion->end_date && now()->gt($promotion->end_date)) {
                $promotion->update(['is_active' => false]);
            }
        });
    }
}

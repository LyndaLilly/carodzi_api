<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'delivery_address',
        'delivery_location',
        'delivery_fee',
        'payment_reference',
        'payment_method',
        'payment_status',
        'crypto_proof',
        'order_status',
        'total_amount',
    ];

    // Relationships
    public function buyer()
    {
        return $this->belongsTo(Buyer::class, 'buyer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}

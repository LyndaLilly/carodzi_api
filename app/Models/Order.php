<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'buyer_fullname',
        'buyer_email',
        'buyer_phone',
        'buyer_delivery_location',
        'total_amount',
        'payment_method',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'buyer_id',
        'delivery_fullname',
        'delivery_email',
        'delivery_phone',
        'buyer_delivery_location',
        'product_id',
        'seller_id',
        'quantity',
        'price',
        'total_amount',
        'payment_method',
        'status',
        'payment_status',
        'bitcoin_proof',
        'paystack_reference',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(ProductUpload::class, 'product_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}

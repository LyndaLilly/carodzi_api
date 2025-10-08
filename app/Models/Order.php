<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'product_id',
        'product_name',
        'product_price',
        'quantity',
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

    /*--------------------------------
    | 🔗 RELATIONSHIPS
    --------------------------------*/
    
    // Each order belongs to a buyer
    public function buyer()
    {
        return $this->belongsTo(Buyer::class, 'buyer_id');
    }

    // Each order belongs to a seller (product owner)
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    // Each order references a product from productupload
    public function product()
    {
        return $this->belongsTo(ProductUpload::class, 'product_id');
    }
}

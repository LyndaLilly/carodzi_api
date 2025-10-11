<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use HasFactory;

    protected $table = 'product_reviews';

    protected $fillable = [
        'productupload_id',
        'buyer_id',
        'order_id',
        'rating',
        'review',
        'is_approved',
        'is_visible',
    ];

    // 🔗 Relationship to product
    public function product()
    {
        return $this->belongsTo(ProductUpload::class, 'productupload_id');
    }

    // 🔗 Relationship to buyer
    public function buyer()
    {
        return $this->belongsTo(Buyer::class, 'buyer_id');
    }

    // 🔗 Relationship to order
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

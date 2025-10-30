<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'buyer_id',
        'product_id',
        'contact_method',
        'buyer_name',
        'buyer_email',
        'message',
    ];

    // Relationships
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductUpload::class, 'product_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    use HasFactory;

    protected $table = 'seller_profiles';

    protected $fillable = [
        'seller_id',
        'gender',
        'date_of_birth',
        'email',
        'phone_number',
        'country',
        'whatsapp_phone_link',
        'state',
        'city',
        'business_name',
        'category_id',
        'product_service_id',
        'profile_image',
        'profession', // ✅ add this
    ];

    // Relationships
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productService()
    {
        return $this->belongsTo(ProductService::class); 
    }
}

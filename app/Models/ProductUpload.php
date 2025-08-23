<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUpload extends Model
{
    use HasFactory;

    protected $table = 'productupload';

    protected $fillable = [
        'seller_id',
        'category_id',
        'subcategory_id',
        'brand',
        'model',
        'condition',
        'internal_storage',
        'ram',
        'location',
        'address',
        'price',         // For products
        'description',
        'is_active',

        // New fields for services/professionals
        'specialization',
        'qualification',
        'availability',
        'rate',          // For services
    ];

    // Automatically include in JSON
    protected $appends = ['is_professional'];

    // 🔹 Relationships
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
    }

    public function images()
    {
        return $this->hasMany(ProductUploadImage::class, 'productupload_id');
    }


    public function getIsProfessionalAttribute()
    {
        return $this->seller ? (int) $this->seller->is_professional : 0;
    }
}

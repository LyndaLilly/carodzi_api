<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUpload extends Model
{
    use HasFactory;

    protected $table = 'productupload';

    protected $fillable = [
        'name',
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
        'price',
        'description',
        'is_active',

        'specialization',
        'qualification',
        'availability',
        'rate', // For services
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

    public function orders()
    {
        return $this->hasMany(Order::class, 'product_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'productupload_id');
    }

}

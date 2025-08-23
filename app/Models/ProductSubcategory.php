<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSubcategory extends Model
{
    protected $table = 'product_subcategories';
    protected $fillable = ['name', 'category_id'];

    // A subcategory belongs to a category
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}

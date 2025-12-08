<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSubcategory extends Model
{
    protected $table    = 'product_subcategories';
    protected $fillable = ['name', 'category_id'];


    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function products()
    {
        return $this->hasMany(ProductUpload::class, 'subcategory_id');
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductSubcategory; 

class ProductCategory extends Model
{
    protected $fillable = ['name'];

    public function subcategories()
    {
        return $this->hasMany(ProductSubcategory::class, 'category_id');
    }
}

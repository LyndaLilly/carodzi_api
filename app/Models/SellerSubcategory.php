<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSubcategory extends Model
{
    protected $table = 'sellers_subcategory'; 

    protected $fillable = ['category_id', 'name'];

    public function category()
    {
        return $this->belongsTo(SellerCategory::class, 'category_id');
    }
}

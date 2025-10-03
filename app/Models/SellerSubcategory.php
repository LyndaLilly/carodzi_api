<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSubcategory extends Model
{
    protected $table = 'sellers_subcategory'; 

    // 👇 include auto_verify so it can be mass assigned
    protected $fillable = ['category_id', 'name', 'auto_verify'];

    public function category()
    {
        return $this->belongsTo(SellerCategory::class, 'category_id');
    }
}

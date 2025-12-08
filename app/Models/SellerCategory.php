<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerCategory extends Model
{
    protected $table = 'sellers_category'; 

    protected $fillable = ['name'];

    public function subcategories()
    {
        return $this->hasMany(SellerSubcategory::class, 'category_id');
    }


}

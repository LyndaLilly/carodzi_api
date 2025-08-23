<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUploadImage extends Model
{
    use HasFactory;

    protected $table = 'productupload_images';

    protected $fillable = [
        'productupload_id',
        'image_path',
    ];

    public function product()
    {
        return $this->belongsTo(ProductUpload::class, 'productupload_id');
    }
}

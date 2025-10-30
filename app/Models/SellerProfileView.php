<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerProfileView extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'viewer_id',
        'ip_address',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function viewer()
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }
}

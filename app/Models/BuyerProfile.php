<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'gender',
        'date_of_birth',
        'profile_image',
        'about',
        'email',
        'mobile_number',
        'whatsapp_phone_link',
        'country',
        'state',
        'city',
    ];

    public function buyer()
    {
        return $this->belongsTo(Buyer::class, 'buyer_id');
    }
}

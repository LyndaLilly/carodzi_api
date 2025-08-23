<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalProfile extends Model
{
    use HasFactory;

    protected $table = 'professional_profiles';

    protected $fillable = [
        'seller_id',
        'gender',
        'date_of_birth',
        'profile_image',
        'about',
        'business_email',
        'mobile_number',
        'whatsapp_phone_link',
        'country',
        'state',
        'city',
        'business_name',
        'verification_number',
        'school_name',
        'graduation_year',
        'experience_years',
        'certificate_file',
        'bank_name',
        'business_bank_name',
        'business_bank_account',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}

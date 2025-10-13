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

    // ✅ Virtual attribute for WhatsApp link
    protected $appends = ['whatsapp_link'];

    public function getWhatsappLinkAttribute()
    {
        if (! $this->mobile_number) return null;

        $raw = preg_replace('/\D/', '', $this->mobile_number);
        if (! str_starts_with($raw, '234')) {
            $raw = '234' . ltrim($raw, '0');
        }
        return "https://wa.me/{$raw}";
    }
}

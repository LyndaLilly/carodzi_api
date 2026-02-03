<?php
namespace App\Models;

use App\Models\Subscription;
use App\Models\SellerVerificationPayment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Seller extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'phone',
        'password',
        'role',
        'views',
        'verification_code',
        'verified',
        'profile_updated',
        'is_professional',
        'status',
        'is_subscribed',
        'subscription_type',
        'subscription_expires_at',
        'email_verified_at',
        'password_reset_code',
        'password_reset_sent_at',
        'category_id',
        'sub_category_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    protected $casts = [
        'verified'                => 'boolean',
        'profile_updated'         => 'boolean',
        'is_professional'         => 'boolean',
        'status'                  => 'boolean',
        'is_subscribed'           => 'boolean',
        'subscription_expires_at' => 'datetime',
        'email_verified_at'       => 'datetime',
        'password_reset_sent_at'  => 'datetime',
        'category_id'             => 'integer',
        'sub_category_id'         => 'integer',
        'product_id'              => 'integer',
        'sub_product_id'          => 'integer',
        'views'                   => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(SellerCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(SellerSubcategory::class, 'sub_category_id');
    }

    public function productcategory()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function productsubcategory()
    {
        return $this->belongsTo(ProductSubcategory::class, 'sub_category_id');
    }

    public function profile()
    {
        return $this->hasOne(OtherProfile::class, 'seller_id', 'id');
    }

    public function professionalProfile()
    {
        return $this->hasOne(ProfessionalProfile::class, 'seller_id', 'id');
    }

    public function products()
    {
        return $this->hasMany(\App\Models\ProductUpload::class, 'seller_id', 'id');
    }

    public function productCount(): int
    {
        return $this->products()->count();
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function profileViews()
    {
        return $this->hasMany(\App\Models\SellerProfileView::class, 'seller_id');
    }

    public function notifications()
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->orderBy('created_at', 'desc');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function hasActiveSubscription(): bool
    {
        if (! $this->subscription) {
            return false;
        }

        return $this->subscription->isValid();
    }

    public function verificationPayments()
    {
        return $this->hasMany(SellerVerificationPayment::class);
    }

}

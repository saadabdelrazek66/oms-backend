<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'bank_account',
        'instapay',
        'wallet',
        'social_links',
    ];

    // تحويل حقل السوشيال ميديا تلقائياً من وإلى مصفوفة
    protected $casts = [
        'social_links' => 'array',
    ];

    // علاقة العميل بجهات الاتصال (One-to-Many)
    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function contentPlans()
    {
        return $this->hasMany(ContentPlan::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use LogsActivity;
    protected $fillable = [
        'name',
        'phones',
        'emails',
        'bank_name',
        'bank_branch',
        'bank_account',
        'instapay',
        'wallet',
        'social_links',
    ];

    protected $casts = [
        'social_links' => 'array',
        'phones' => 'array',
        'emails' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('Client')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} بيانات العميل");
    }

    // علاقة العميل بجهات الاتصال (One-to-Many)
    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function contentPlans()
    {
        return $this->hasMany(ContentPlan::class);
    }

    // علاقة العميل ببيانات الدخول (الخزنة)
    public function credentials()
    {
        return $this->hasMany(ClientCredential::class);
    }

    /**
     * علاقة العميل بروابط درايف الخاصة به
     */
    public function driveLinks()
    {
        return $this->hasMany(ClientDriveLink::class);
    }
}

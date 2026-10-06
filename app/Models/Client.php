<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Enums\DriveLinkType;

class Client extends Model
{
    use LogsActivity, SoftDeletes;
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
        'group_links',
        'logo',
    ];


    protected $casts = [
        'social_links' => 'array',
        'group_links' => 'array',
        'phones' => 'array',
        'emails' => 'array',
    ];

    protected $appends = ['logo_url'];

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->logo) {
                    return asset('uploads/clients/' . $this->logo);
                }
                return null;
            }
        );
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

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

    public function plans()
    {
        return $this->contentPlans();
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

    /**
     * التحقق مما إذا كان العميل يمتلك نوع رابط معين
     */
    public function hasDriveLinkType($type)
    {
        return $this->driveLinks()->where('title', $type)->exists();
    }
}

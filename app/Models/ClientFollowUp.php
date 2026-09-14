<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientFollowUp extends Model
{
    use LogsActivity;
    protected $fillable = ['content_plan_id', 'user_id', 'content', 'image_path',];

    protected $appends = ['image_url'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('ClientFollowUp')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} سجل متابعة للعميل");
    }

    // الخطة المرتبطة
    public function plan()
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    // الموظف الذي كتب التحديث
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

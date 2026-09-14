<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlanReviewHistory extends Model
{
    use LogsActivity;
    protected $fillable = [
        'content_plan_id',
        'reviewer_id',
        'action',
        'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('PlanReviewHistory')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} سجل مراجعة الخطة");
    }

    // الخطة المرتبطة
    public function plan()
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    // المراجع الذي قام بالقرار
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}

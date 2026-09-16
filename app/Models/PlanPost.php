<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlanPost extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    // تحويل البيانات (Casting)
    protected $casts = [
        'target_date' => 'date',
        'deadline' => 'datetime',
        'ad_platform' => 'array',
        'delivered_at' => 'datetime',
        'execution_started_at' => 'datetime',
        'department_approved_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'published_links' => 'array',
        'rejection_history' => 'array',
        'manager_rejection_history' => 'array',
        'reviewer_ids' => 'array',
        'reviewers_statuses' => 'array',
        'locked_fields' => 'array',
        'finance_cost' => 'decimal:2',
        'finance_days' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('PlanPost')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} منشور داخل الخطة");
    }

    // العلاقات
    public function plan() {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }
    public function designer() {
        return $this->belongsTo(User::class, 'designer_id');
    }
    public function reviewer() {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
    public function contentPlan()
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }
    public function messages()
    {
        return $this->hasMany(PostMessage::class, 'plan_post_id');
    }
}

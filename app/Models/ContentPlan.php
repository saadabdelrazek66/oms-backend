<?php

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;

class ContentPlan extends Model
{
    protected $fillable = [
        'client_id',
        'plan_type',
        'requires_review',
        'status',
        'planned_delivery_date',
        'actual_delivery_date',
        'planned_review_date',
        'actual_review_date',
        'start_date',
        'end_date',
        'reference_links',
        'final_link',
        'notes',
    ];

    protected $casts = [
        'planned_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'planned_review_date' => 'datetime',
        'actual_review_date' => 'datetime',
        'reference_links' => 'array',
    ];

    protected static function booted()
    {
        static::created(function ($plan) {
            if ($plan->start_date && $plan->end_date) {
                // دالة CarbonPeriod تجلب لنا جميع الأيام بين التاريخين
                $period = CarbonPeriod::create($plan->start_date, $plan->end_date);
                $posts = [];

                foreach ($period as $date) {
                    $posts[] = [
                        'content_plan_id' => $plan->id,
                        'target_date' => $date->format('Y-m-d'), // تاريخ اليوم
                        'actual_publish_status' => 'لم يتم',      // حالة افتراضية
                        'finance_status' => 'غير ممول',          // حالة افتراضية
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                PlanPost::insert($posts);
            }
        });
    }

    // علاقة الخطة بالموظفين
    public function users()
    {
        return $this->belongsToMany(User::class, 'content_plan_user')
            ->withPivot('task_role')
            ->withTimestamps();
    }

    // العلاقة الجديدة: الخطة تابعة لعميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewHistories()
    {
        return $this->hasMany(PlanReviewHistory::class)->orderBy('created_at', 'desc');
    }

    // علاقة الخطة بسجل متابعة العميل
    public function clientFollowUps()
    {
        return $this->hasMany(ClientFollowUp::class)->orderBy('created_at', 'desc');
    }

    public function posts()
    {
        return $this->hasMany(PlanPost::class)->orderBy('target_date', 'asc');
    }
}

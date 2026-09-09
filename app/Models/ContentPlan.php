<?php

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;

class ContentPlan extends Model
{
    protected $fillable = [
        'name', // إضافة الاسم هنا
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
        // 1. توليد الاسم تلقائياً قبل الإنشاء
        static::creating(function ($plan) {
            $plan->name = self::generatePlanName($plan);
        });

        // 2. تحديث الاسم تلقائياً في حال تعديل (العميل، النوع، أو التواريخ)
        static::updating(function ($plan) {
            $plan->name = self::generatePlanName($plan);
        });

        // 3. إنشاء المنشورات التلقائية المبدئية (بحد أقصى 3 أيام لتجنب الزحام)
        static::created(function ($plan) {
            if ($plan->start_date && $plan->end_date) {
                $period = CarbonPeriod::create($plan->start_date, $plan->end_date);
                $posts = [];
                $limit = 3; // الحد الأقصى للأيام المبدئية التي سيتم إنشاؤها
                $count = 0;

                foreach ($period as $date) {
                    if ($count >= $limit) {
                        break; // التوقف عند الوصول للحد الأقصى
                    }

                    $posts[] = [
                        'content_plan_id' => $plan->id,
                        'target_date' => $date->format('Y-m-d'),
                        'actual_publish_status' => 'لم يتم',
                        'finance_status' => 'غير ممول',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $count++;
                }

                if (!empty($posts)) {
                    PlanPost::insert($posts);
                }
            }
        });
    }

    // --- دالة مساعدة لتوليد اسم الخطة بذكاء ---
    private static function generatePlanName($plan)
    {
        // جلب اسم العميل (لتجنب الأخطاء إذا لم يكن العميل موجوداً بعد)
        $clientName = 'عميل غير محدد';
        if ($plan->client_id) {
            $client = Client::find($plan->client_id);
            $clientName = $client ? $client->name : 'عميل غير محدد';
        }

        $type = $plan->plan_type ?? 'خطة';
        $start = $plan->start_date ? \Carbon\Carbon::parse($plan->start_date)->format('Y-m-d') : '';
        $end = $plan->end_date ? \Carbon\Carbon::parse($plan->end_date)->format('Y-m-d') : '';

        // النتيجة: اسم العميل - نوع الخطة (من 2026-09-01 إلى 2026-09-30)
        return "{$clientName} - {$type} (من {$start} إلى {$end})";
    }

    // علاقة الخطة بالموظفين
    public function users()
    {
        return $this->belongsToMany(User::class, 'content_plan_user')
            ->withPivot('task_role')
            ->withTimestamps();
    }

    // علاقة الخطة بعميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewHistories()
    {
        return $this->hasMany(PlanReviewHistory::class)->orderBy('created_at', 'desc');
    }

    public function clientFollowUps()
    {
        return $this->hasMany(ClientFollowUp::class)->orderBy('created_at', 'desc');
    }

    public function posts()
    {
        return $this->hasMany(PlanPost::class)->orderBy('target_date', 'asc');
    }
}

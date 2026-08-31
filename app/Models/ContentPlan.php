<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPlan extends Model
{
    protected $fillable = [
        'client_id', // تم التعديل هنا
        'plan_type',
        'planned_delivery_date',
        'actual_delivery_date',
        'planned_review_date',
        'actual_review_date',
        'final_link',
        'notes',
    ];

    protected $casts = [
        'planned_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'planned_review_date' => 'datetime',
        'actual_review_date' => 'datetime',
    ];

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
}

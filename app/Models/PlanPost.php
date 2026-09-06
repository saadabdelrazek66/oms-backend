<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanPost extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    // تحويل البيانات (Casting)
    protected $casts = [
        'target_date' => 'date',
        'deadline' => 'datetime',
        'ad_platform' => 'array',
        'rejection_history' => 'array',
        'manager_rejection_history' => 'array',
        'reviewer_ids' => 'array',
        'reviewers_statuses' => 'array',
    ];

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
}

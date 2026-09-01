<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanReviewHistory extends Model
{
    protected $fillable = [
        'content_plan_id',
        'reviewer_id',
        'action',
        'notes',
    ];

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

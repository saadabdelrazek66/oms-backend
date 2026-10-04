<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_plan_id',
        'plan_item_estimate_id',
        'item_name',
        'quantity',
        'hours_per_unit',
        'total_hours',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'hours_per_unit' => 'float',
        'total_hours' => 'float',
    ];

    public function plan()
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    public function estimate()
    {
        return $this->belongsTo(PlanItemEstimate::class, 'plan_item_estimate_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlanItemEstimate extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'category_id',
        'name',
        'estimated_hours',
        'unit',
        'sort_order',
    ];

    protected $casts = [
        'estimated_hours' => 'float',
        'sort_order' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('PlanItemEstimate')
            ->setDescriptionForEvent(fn(string $eventName) => "تم {$eventName} عنصر وساعات تقديرية في الإعدادات");
    }

    public function category()
    {
        return $this->belongsTo(PlanItemCategory::class, 'category_id');
    }
}

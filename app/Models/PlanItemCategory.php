<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlanItemCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'sort_order',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('PlanItemCategory')
            ->setDescriptionForEvent(fn(string $eventName) => "تم {$eventName} نوع خطة في الإعدادات");
    }

    public function items()
    {
        return $this->hasMany(PlanItemEstimate::class, 'category_id')->orderBy('sort_order')->orderBy('id');
    }
}

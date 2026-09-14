<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class Department extends Model
{
    use LogsActivity;
    protected $fillable = ['name', 'description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('Department')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} قسم");
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'department_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    // المشاريع التابعة لهذا القسم
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'department_project');
    }
}

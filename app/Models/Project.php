<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Project extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
    ];


    protected $appends = ['progress'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('Project')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} المشروع");
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    // علاقة المشروع بالأقسام (Many-to-Many)
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_project');
    }

    // علاقة المشروع بأعضاء الفريق المصرح لهم بالدخول (Many-to-Many)
    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user');
    }

    // المهام التابعة لهذا المشروع
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * حساب نسبة إنجاز المشروع بناءً على المهام المكتملة
     */
    public function getProgressAttribute()
    {
        // استخدام العلاقات المحملة لمنع مشكلة (N+1 Query Problem)
        if ($this->relationLoaded('tasks')) {
            $totalTasks = $this->tasks->count();
            if ($totalTasks === 0)
                return 0;
            $completedTasks = $this->tasks->where('status', 'completed')->count();
            return round(($completedTasks / $totalTasks) * 100);
        }

        // في حالة استدعاء المشروع منفرداً
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0)
            return 0;

        $completedTasks = $this->tasks()->where('status', 'completed')->count();
        return round(($completedTasks / $totalTasks) * 100);
    }
}

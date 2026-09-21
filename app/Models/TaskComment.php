<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaskComment extends Model
{
    use LogsActivity;
    protected $fillable = ['task_id', 'user_id', 'comment', 'is_deleted', 'is_edited'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('TaskComment')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} تعليق على المهمة");
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

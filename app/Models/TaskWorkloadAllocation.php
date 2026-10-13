<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PlanPost;
use App\Models\Task;
use App\Modules\QuickTasks\Models\QuickTask;

class TaskWorkloadAllocation extends Model
{
    protected $table = 'task_workload_allocations';

    protected $fillable = [
        'user_id',
        'allocatable_type', // plan_post, task, quick_task
        'allocatable_id',
        'work_date',
        'allocated_hours',
        'is_shifted',
        'shifted_from_date',
        'shift_reason'
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'shifted_from_date' => 'date:Y-m-d',
        'allocated_hours' => 'float',
        'is_shifted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * جلب سجل المهمة الأصلي المرتبط بهذا التخصيص
     */
    public function getTaskItem()
    {
        switch ($this->allocatable_type) {
            case 'plan_post':
                return PlanPost::find($this->allocatable_id);
            case 'task':
                return Task::find($this->allocatable_id);
            case 'quick_task':
                return QuickTask::find($this->allocatable_id);
            default:
                return null;
        }
    }
}

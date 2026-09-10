<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
    ];

    // علاقة المهمة بالمشروع
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // علاقة المهمة بمن أنشأها
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // علاقة المهمة بالموظف المسؤول عن تنفيذها
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}

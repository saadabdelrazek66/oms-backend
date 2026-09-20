<?php

namespace App\Modules\QuickTasks\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class QuickTask extends Model
{
    protected $table = 'quick_tasks';

    protected $fillable = [
        'created_by', 'assigned_to', 'title', 'description',
        'voice_record_path', 'deadline', 'status'
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    // علاقة صانع المهمة (المدير)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // علاقة المنفذ (الموظف)
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // علاقة سجل المراجعات والرفض (الـ Timeline)
    public function feedbackLogs()
    {
        return $this->hasMany(QuickTaskFeedbackLog::class)->latest();
    }
}
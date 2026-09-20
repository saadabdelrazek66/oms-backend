<?php

namespace App\Modules\QuickTasks\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class QuickTaskFeedbackLog extends Model
{
    protected $table = 'quick_task_feedback_logs';

    protected $fillable = [
        'quick_task_id', 'reviewer_id', 'feedback_text',
        'voice_record_path', 'status_changed_to'
    ];

    public function quickTask()
    {
        return $this->belongsTo(QuickTask::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
<?php

namespace App\Modules\QuickTasks\Services;

use App\Modules\QuickTasks\Models\QuickTask;
use Illuminate\Support\Facades\File;

class QuickTaskService
{
    public function createTask(array $data, $voiceFile = null, $creatorId = null)
    {
        $voicePath = null;

        if ($voiceFile) {
            $fileName = time() . '_' . uniqid() . '.' . $voiceFile->getClientOriginalExtension();
            $voiceFile->move(public_path('uploads/quick_tasks/voices'), $fileName);
            $voicePath = 'uploads/quick_tasks/voices/' . $fileName;
        }

        return QuickTask::create([
            'created_by' => $creatorId,
            'assigned_to' => $data['assigned_to'],
            'title' => $data['title'] ?? 'مهمة سريعة بدون عنوان',
            'description' => $data['description'] ?? null,
            'voice_record_path' => $voicePath,
            'deadline' => $data['deadline'],
            'status' => 'pending'
        ]);
    }

    public function reviewTask(QuickTask $task, array $data, $voiceFile = null, $reviewerId = null)
    {
        $voicePath = null;

        if ($voiceFile) {
            $fileName = time() . '_feedback_' . uniqid() . '.' . $voiceFile->getClientOriginalExtension();
            $voiceFile->move(public_path('uploads/quick_tasks/voices'), $fileName);
            $voicePath = 'uploads/quick_tasks/voices/' . $fileName;
        }

        $feedbackLog = $task->feedbackLogs()->create([
            'reviewer_id' => $reviewerId,
            'feedback_text' => $data['feedback_text'] ?? null,
            'voice_record_path' => $voicePath,
            'status_changed_to' => $data['status_changed_to'],
        ]);

        $task->update([
            'status' => $data['status_changed_to']
        ]);

        return $feedbackLog;
    }

    public function submitTask(QuickTask $task, array $data = [], $voiceFile = null, $userId = null)
    {
        $voicePath = null;

        if ($voiceFile) {
            $fileName = time() . '_submit_' . uniqid() . '.' . $voiceFile->getClientOriginalExtension();
            $voiceFile->move(public_path('uploads/quick_tasks/voices'), $fileName);
            $voicePath = 'uploads/quick_tasks/voices/' . $fileName;
        }

        $task->feedbackLogs()->create([
            'reviewer_id' => $userId ?? $task->assigned_to,
            'feedback_text' => $data['feedback_text'] ?? null,
            'voice_record_path' => $voicePath,
            'status_changed_to' => 'under_review',
        ]);

        $task->update(['status' => 'under_review']);
        $task->load(['creator', 'assignee', 'feedbackLogs.reviewer']);
        return $task;
    }

    public function updateTask(QuickTask $task, array $data, $voiceFile = null)
    {
        $voicePath = $task->voice_record_path;

        if ($voiceFile) {
            if ($voicePath && File::exists(public_path($voicePath))) {
                File::delete(public_path($voicePath));
            }

            $fileName = time() . '_' . uniqid() . '.' . $voiceFile->getClientOriginalExtension();
            $voiceFile->move(public_path('uploads/quick_tasks/voices'), $fileName);
            $voicePath = 'uploads/quick_tasks/voices/' . $fileName;
        }

        $task->update(array_filter([
            'assigned_to' => $data['assigned_to'] ?? $task->assigned_to,
            'title' => $data['title'] ?? $task->title,
            'description' => $data['description'] ?? $task->description,
            'voice_record_path' => $voicePath,
            'deadline' => $data['deadline'] ?? $task->deadline,
        ]));

        return $task;
    }

    public function deleteTask(QuickTask $task)
    {
        if ($task->voice_record_path && File::exists(public_path($task->voice_record_path))) {
            File::delete(public_path($task->voice_record_path));
        }

        foreach ($task->feedbackLogs as $log) {
            if ($log->voice_record_path && File::exists(public_path($log->voice_record_path))) {
                File::delete(public_path($log->voice_record_path));
            }
        }

        return $task->delete();
    }
}
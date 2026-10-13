<?php

namespace App\Modules\QuickTasks\Services;

use App\Models\User;
use App\Modules\QuickTasks\Models\QuickTask;
use App\Modules\Notifications\Notifications\SystemNotification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class QuickTaskService
{
    public function createTask(array $data, $voiceFile = null, $attachmentFile = null, $creatorId = null)
    {
        $voicePath = null;
        $attachmentPath = null;

        if ($voiceFile) {
            $type = QuickTaskAttachmentSecurity::classifyType($voiceFile->getClientOriginalName());
            if ($type === 'audio') {
                $voicePath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'voice');
            } else {
                $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'attachment');
            }
        }

        if ($attachmentFile) {
            $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($attachmentFile, 'attachment');
        }

        $task = QuickTask::create([
            'created_by' => $creatorId,
            'assigned_to' => $data['assigned_to'],
            'title' => $data['title'] ?? 'مهمة سريعة بدون عنوان',
            'description' => $data['description'] ?? null,
            'voice_record_path' => $voicePath,
            'attachment_path' => $attachmentPath,
            'deadline' => $data['deadline'],
            'estimated_hours' => isset($data['estimated_hours']) ? (float)$data['estimated_hours'] : 1.00,
            'is_urgent' => !empty($data['is_urgent']),
            'status' => 'pending'
        ]);

        // 🔔 إشعار داخلي: إسناد مهمة سريعة جديدة للموظف
        try {
            $assignee = User::find($task->assigned_to);
            $creator = $creatorId ? User::find($creatorId) : null;
            if ($assignee && (int)$assignee->id !== (int)$creatorId) {
                $creatorName = $creator ? $creator->name : 'الإدارة';
                $deadlineFormatted = $task->deadline ? $task->deadline->format('Y-m-d h:i A') : '';
                Notification::send($assignee, new SystemNotification([
                    'title' => 'مهمة سريعة جديدة 🎯',
                    'body' => "قام {$creatorName} بإسناد مهمة سريعة جديدة لك: \"{$task->title}\"" . ($deadlineFormatted ? " (الديدلاين: {$deadlineFormatted})" : ''),
                    'url' => "/quick-tasks?task_id={$task->id}",
                    'icon' => 'clipboard-check',
                    'type' => 'quick_task_created'
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning('QuickTask Notification Error (createTask): ' . $e->getMessage());
        }

        // 🔄 تشغيل الترحيل التلقائي إذا كانت المهمة عاجلة
        if (!empty($task->is_urgent) && $task->assigned_to && $task->deadline) {
            try {
                $urgentDate = \Carbon\Carbon::parse($task->deadline);
                $taskTitle = "مهمة سريعة: " . ($task->title ?? '');
                app(\App\Services\WorkloadCascadingService::class)->autoCascadeWorkload((int) $task->assigned_to, $urgentDate, $task, $taskTitle, 'quick_task');
            } catch (\Throwable $e) {
                Log::error("Error in autoCascadeWorkload for quick task {$task->id}: " . $e->getMessage());
            }
        }

        return $task;
    }

    public function reviewTask(QuickTask $task, array $data, $voiceFile = null, $attachmentFile = null, $reviewerId = null)
    {
        $voicePath = null;
        $attachmentPath = null;

        if ($voiceFile) {
            $type = QuickTaskAttachmentSecurity::classifyType($voiceFile->getClientOriginalName());
            if ($type === 'audio') {
                $voicePath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'review_voice');
            } else {
                $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'review_attachment');
            }
        }

        if ($attachmentFile) {
            $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($attachmentFile, 'review_attachment');
        }

        $feedbackLog = $task->feedbackLogs()->create([
            'reviewer_id' => $reviewerId,
            'feedback_text' => $data['feedback_text'] ?? null,
            'voice_record_path' => $voicePath,
            'attachment_path' => $attachmentPath,
            'status_changed_to' => $data['status_changed_to'],
        ]);

        $task->update([
            'status' => $data['status_changed_to']
        ]);

        // 🔔 إشعار داخلي: نتيجة المراجعة (اعتماد أو طلب تعديل) للموظف المنفذ
        try {
            $reviewer = $reviewerId ? User::find($reviewerId) : null;
            $reviewerName = $reviewer ? $reviewer->name : 'الإدارة';
            $assignee = User::find($task->assigned_to);

            if ($assignee && (int)$assignee->id !== (int)$reviewerId) {
                if ($data['status_changed_to'] === 'completed') {
                    Notification::send($assignee, new SystemNotification([
                        'title' => 'تم اعتماد المهمة السريعة بنجاح 🎉',
                        'body' => "تم اعتماد واكتمال مهمتك \"{$task->title}\" بنجاح من قِبل {$reviewerName}.",
                        'url' => "/quick-tasks?task_id={$task->id}",
                        'icon' => 'check-circle',
                        'type' => 'quick_task_approved'
                    ]));
                } elseif ($data['status_changed_to'] === 'rejected') {
                    $notes = !empty($data['feedback_text']) ? ' ملاحظات: ' . Str::limit($data['feedback_text'], 60) : '';
                    Notification::send($assignee, new SystemNotification([
                        'title' => 'طلب تعديل وملاحظات على المهمة ⚠️',
                        'body' => "طلب {$reviewerName} تعديلات على مهمة \"{$task->title}\".{$notes}",
                        'url' => "/quick-tasks?task_id={$task->id}",
                        'icon' => 'exclamation-circle',
                        'type' => 'quick_task_rejected'
                    ]));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('QuickTask Notification Error (reviewTask): ' . $e->getMessage());
        }

        return $feedbackLog;
    }

    public function submitTask(QuickTask $task, array $data = [], $voiceFile = null, $attachmentFile = null, $userId = null)
    {
        $voicePath = null;
        $attachmentPath = null;

        if ($voiceFile) {
            $type = QuickTaskAttachmentSecurity::classifyType($voiceFile->getClientOriginalName());
            if ($type === 'audio') {
                $voicePath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'submit_voice');
            } else {
                $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'submit_attachment');
            }
        }

        if ($attachmentFile) {
            $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($attachmentFile, 'submit_attachment');
        }

        $task->feedbackLogs()->create([
            'reviewer_id' => $userId ?? $task->assigned_to,
            'feedback_text' => $data['feedback_text'] ?? null,
            'voice_record_path' => $voicePath,
            'attachment_path' => $attachmentPath,
            'status_changed_to' => 'under_review',
        ]);

        $task->update(['status' => 'under_review']);
        $task->load(['creator', 'assignee', 'feedbackLogs.reviewer']);

        // 🔔 إشعار داخلي: تسليم المهمة للمراجعة والاعتماد (إشعار لصاحب التكليف أو مديري النظام)
        try {
            $submittingUser = User::find($userId ?? $task->assigned_to);
            $employeeName = $submittingUser ? $submittingUser->name : 'الموظف المنفذ';

            $recipients = collect();
            if ($task->created_by) {
                $creator = User::find($task->created_by);
                if ($creator && (int)$creator->id !== (int)($submittingUser?->id)) {
                    $recipients->push($creator);
                }
            }

            // إذا لم يكن منشئ المهمة موجوداً أو كان المنشئ هو نفسه المنفذ، يتم إشعار المديرين
            if ($recipients->isEmpty()) {
                $managers = User::where('role', 'manager')
                    ->where('id', '!=', $submittingUser?->id ?? 0)
                    ->get();
                $recipients = $recipients->merge($managers);
            }

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients->unique('id'), new SystemNotification([
                    'title' => 'تسليم مهمة سريعة للمراجعة 🚀',
                    'body' => "قام {$employeeName} بتسليم مهمة \"{$task->title}\" للمراجعة والاعتماد.",
                    'url' => "/quick-tasks?task_id={$task->id}",
                    'icon' => 'paper-plane',
                    'type' => 'quick_task_submitted'
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning('QuickTask Notification Error (submitTask): ' . $e->getMessage());
        }

        return $task;
    }

    public function updateTask(QuickTask $task, array $data, $voiceFile = null, $attachmentFile = null)
    {
        $oldAssigneeId = (int)$task->assigned_to;
        $newAssigneeId = isset($data['assigned_to']) ? (int)$data['assigned_to'] : $oldAssigneeId;
        $authUserId = auth()->id();
        $updaterName = auth()->user() ? auth()->user()->name : 'الإدارة';

        $voicePath = $task->voice_record_path;
        $attachmentPath = $task->attachment_path;

        if ($voiceFile) {
            if ($voicePath && File::exists(public_path($voicePath))) {
                File::delete(public_path($voicePath));
            }

            $type = QuickTaskAttachmentSecurity::classifyType($voiceFile->getClientOriginalName());
            if ($type === 'audio') {
                $voicePath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'voice_update');
            } else {
                $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($voiceFile, 'attachment_update');
            }
        }

        if ($attachmentFile) {
            if ($attachmentPath && File::exists(public_path($attachmentPath))) {
                File::delete(public_path($attachmentPath));
            }
            $attachmentPath = QuickTaskAttachmentSecurity::storeSafely($attachmentFile, 'attachment_update');
        }

        $updateFields = [
            'assigned_to' => $newAssigneeId,
            'title' => array_key_exists('title', $data) ? $data['title'] : $task->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $task->description,
            'voice_record_path' => $voicePath,
            'attachment_path' => $attachmentPath,
            'deadline' => $data['deadline'] ?? $task->deadline,
        ];

        if (array_key_exists('estimated_hours', $data)) {
            $updateFields['estimated_hours'] = (float)$data['estimated_hours'];
        }
        if (array_key_exists('is_urgent', $data)) {
            $updateFields['is_urgent'] = (bool)$data['is_urgent'];
        }

        $task->update($updateFields);

        // 🔔 إشعار داخلي: عند تعديل المهمة أو إعادة إسنادها لموظف آخر
        try {
            if ($oldAssigneeId !== $newAssigneeId) {
                $newAssignee = User::find($newAssigneeId);
                if ($newAssignee && (int)$newAssignee->id !== (int)$authUserId) {
                    Notification::send($newAssignee, new SystemNotification([
                        'title' => 'تم تحويل مهمة سريعة لك 🎯',
                        'body' => "تم إسناد مهمة \"{$task->title}\" إليك بواسطة {$updaterName}.",
                        'url' => "/quick-tasks?task_id={$task->id}",
                        'icon' => 'clipboard-check',
                        'type' => 'quick_task_reassigned'
                    ]));
                }
            } else {
                $assignee = User::find($newAssigneeId);
                if ($assignee && (int)$assignee->id !== (int)$authUserId) {
                    Notification::send($assignee, new SystemNotification([
                        'title' => 'تحديث في المهمة السريعة ✏️',
                        'body' => "قام {$updaterName} بتحديث تفاصيل مهمة \"{$task->title}\".",
                        'url' => "/quick-tasks?task_id={$task->id}",
                        'icon' => 'pencil',
                        'type' => 'quick_task_updated'
                    ]));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('QuickTask Notification Error (updateTask): ' . $e->getMessage());
        }

        return $task;
    }

    public function deleteTask(QuickTask $task)
    {
        $taskTitle = $task->title;
        $assigneeId = $task->assigned_to;
        $authUserId = auth()->id();
        $deleterName = auth()->user() ? auth()->user()->name : 'الإدارة';

        if ($task->voice_record_path && File::exists(public_path($task->voice_record_path))) {
            File::delete(public_path($task->voice_record_path));
        }

        if ($task->attachment_path && File::exists(public_path($task->attachment_path))) {
            File::delete(public_path($task->attachment_path));
        }

        foreach ($task->feedbackLogs as $log) {
            if ($log->voice_record_path && File::exists(public_path($log->voice_record_path))) {
                File::delete(public_path($log->voice_record_path));
            }
            if ($log->attachment_path && File::exists(public_path($log->attachment_path))) {
                File::delete(public_path($log->attachment_path));
            }
        }

        $deleted = $task->delete();

        // 🔔 إشعار داخلي: إلغاء / حذف المهمة للموظف المكلف
        try {
            $assignee = User::find($assigneeId);
            if ($assignee && (int)$assignee->id !== (int)$authUserId) {
                Notification::send($assignee, new SystemNotification([
                    'title' => 'إلغاء مهمة سريعة 🗑️',
                    'body' => "تم إلغاء وحذف مهمة \"{$taskTitle}\" من قِبل {$deleterName}.",
                    'url' => '/quick-tasks',
                    'icon' => 'trash',
                    'type' => 'quick_task_deleted'
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning('QuickTask Notification Error (deleteTask): ' . $e->getMessage());
        }

        return $deleted;
    }
}
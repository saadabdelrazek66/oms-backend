<?php

namespace App\Modules\QuickTasks\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuickTaskResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'assigned_to' => $this->assigned_to,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'description' => $this->description,
            'voice_record_url' => $this->voice_record_path ? asset($this->voice_record_path) : null,
            'attachment_url' => ($this->attachment_path ? asset($this->attachment_path) : ($this->voice_record_path && \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::classifyType($this->voice_record_path) !== 'audio' ? asset($this->voice_record_path) : null)),
            'attachment_name' => ($this->attachment_path ? basename($this->attachment_path) : ($this->voice_record_path && \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::classifyType($this->voice_record_path) !== 'audio' ? basename($this->voice_record_path) : null)),
            'attachment_type' => \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::classifyType($this->attachment_path ?? $this->voice_record_path),
            'attachment_extension' => ($this->attachment_path ?? $this->voice_record_path) ? strtolower(pathinfo($this->attachment_path ?? $this->voice_record_path, PATHINFO_EXTENSION)) : null,
            'attachment_size' => \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::formatFileSize($this->attachment_path ?? $this->voice_record_path),
            'deadline' => $this->deadline ? $this->deadline->format('Y-m-d H:i:s') : null,
            'status' => $this->status,
            'creator_name' => $this->creator ? $this->creator->name : 'غير محدد',
            'assignee_name' => $this->assignee ? $this->assignee->name : 'غير محدد',
            'creator_phone' => $this->creator ? $this->creator->phone : null,
            'assignee_phone' => $this->assignee ? $this->assignee->phone : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i A') : '',

            'feedback_logs' => FeedbackLogResource::collection($this->whenLoaded('feedbackLogs')),
        ];
    }
}
<?php

namespace App\Modules\QuickTasks\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $this->reviewer ? $this->reviewer->name : 'غير معروف',
            'feedback_text' => $this->feedback_text,
            'voice_record_url' => $this->voice_record_path ? asset($this->voice_record_path) : null,
            'attachment_url' => ($this->attachment_path ? asset($this->attachment_path) : ($this->voice_record_path && \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::classifyType($this->voice_record_path) !== 'audio' ? asset($this->voice_record_path) : null)),
            'attachment_name' => ($this->attachment_path ? basename($this->attachment_path) : ($this->voice_record_path && \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::classifyType($this->voice_record_path) !== 'audio' ? basename($this->voice_record_path) : null)),
            'attachment_type' => \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::classifyType($this->attachment_path ?? $this->voice_record_path),
            'attachment_extension' => ($this->attachment_path ?? $this->voice_record_path) ? strtolower(pathinfo($this->attachment_path ?? $this->voice_record_path, PATHINFO_EXTENSION)) : null,
            'attachment_size' => \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::formatFileSize($this->attachment_path ?? $this->voice_record_path),
            'status_changed_to' => $this->status_changed_to,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i A') : '',
            'created_at_human' => $this->created_at ? $this->created_at->diffForHumans() : '',
        ];
    }
}
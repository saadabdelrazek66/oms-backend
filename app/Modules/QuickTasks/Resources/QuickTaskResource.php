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
            'deadline' => $this->deadline ? $this->deadline->format('Y-m-d H:i:s') : null,
            'status' => $this->status,
            'creator_name' => $this->creator ? $this->creator->name : 'غير محدد',
            'assignee_name' => $this->assignee ? $this->assignee->name : 'غير محدد',
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i A') : '',
            
            'feedback_logs' => FeedbackLogResource::collection($this->whenLoaded('feedbackLogs')),
        ];
    }
}
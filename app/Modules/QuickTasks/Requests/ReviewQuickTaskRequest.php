<?php

namespace App\Modules\QuickTasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewQuickTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true; 
    }

    public function rules()
    {
        return [
            'status_changed_to' => 'required|in:completed,rejected',
            'feedback_text' => 'nullable|string',
            'voice_record' => 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,ogg,webm|max:10240',
        ];
    }
}
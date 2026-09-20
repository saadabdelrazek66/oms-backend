<?php

namespace App\Modules\QuickTasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuickTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'assigned_to' => 'required|exists:users,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'voice_record' => 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,ogg,webm|max:10240', 
            'deadline' => 'required|date',
        ];
    }
}
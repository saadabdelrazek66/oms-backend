<?php

namespace App\Modules\QuickTasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuickTaskRequest extends FormRequest
{
    public function authorize()
    {
        $user = $this->user();
        if (!$user) return false;

        $role = is_object($user->role) ? $user->role->value : $user->role;
        $quickTask = $this->route('quickTask');

        if ($quickTask && (int)$user->id === (int)$quickTask->assigned_to && $role !== 'manager') {
            return false;
        }

        return $role === 'manager' || ($quickTask && (int)$user->id === (int)$quickTask->created_by);
    }

    public function rules()
    {
        return [
            'assigned_to' => 'nullable|exists:users,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'voice_record' => 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,ogg,webm|max:10240', 
            'deadline' => 'nullable|date',
        ];
    }
}
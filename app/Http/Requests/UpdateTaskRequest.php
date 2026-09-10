<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        $user = auth()->user();

        if ($user->role->value === 'manager') return true;

        // يُسمح للموظف بالتعديل إذا كان هو المسؤول عن المهمة أو هو من أنشأها
        return $task->assigned_to === $user->id || $task->created_by === $user->id;
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:todo,in_progress,in_review,completed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
        ];

        if (auth()->user()->role->value === 'manager') {
            $rules['assigned_to'] = 'sometimes|required|exists:users,id';
        }

        return $rules;
    }
}

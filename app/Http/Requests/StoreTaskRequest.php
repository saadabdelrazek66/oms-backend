<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project'); // جلب المشروع من الرابط
        $user = auth()->user();

        // المدير مسموح له، والموظف مسموح له فقط إذا كان عضواً في المشروع
        if ($user->role->value === 'manager') return true;
        return $project->users()->where('user_id', $user->id)->exists();
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:todo,in_progress,in_review,completed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
        ];

        // نطلب حقل "المسؤول" إجبارياً من المدير فقط
        if (auth()->user()->role->value === 'manager') {
            $rules['assigned_to'] = 'required|exists:users,id';
        }

        return $rules;
    }
}

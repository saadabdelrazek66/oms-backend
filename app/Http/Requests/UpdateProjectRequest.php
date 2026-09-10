<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->role->value === 'manager';
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|min:3|max:255',
            'description' => 'nullable|string|max:2000',
            'status' => 'nullable|in:قيد التخطيط,جاري التنفيذ,مكتمل,متوقف',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department_ids' => 'sometimes|required|array|min:1',
            'department_ids.*' => 'exists:departments,id',
            'user_ids' => 'sometimes|required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يوافق تاريخ البداية.',
            'department_ids.required' => 'يجب إبقاء المشروع مربوطاً بقسم واحد على الأقل.',
        ];
    }
}

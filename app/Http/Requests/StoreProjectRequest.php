<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // فقط المدير من يحق له إنشاء مشاريع
        return auth()->user()->role->value === 'manager';
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:2000',
            'status' => 'nullable|in:قيد التخطيط,جاري التنفيذ,مكتمل,متوقف',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',

            // التأكد من إرسال مصفوفة أقسام وأن الأقسام موجودة فعلاً
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'exists:departments,id',

            // التأكد من إرسال مصفوفة موظفين وأنهم موجودين فعلاً
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المشروع مطلوب.',
            'start_date.required' => 'تاريخ بدء المشروع مطلوب.',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يوافق تاريخ البداية.',
            'department_ids.required' => 'يجب ربط المشروع بقسم واحد على الأقل.',
            'user_ids.required' => 'يجب تحديد موظف واحد على الأقل للوصول إلى هذا المشروع.',
        ];
    }
}

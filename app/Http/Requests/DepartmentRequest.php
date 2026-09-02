<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    /**
     * التحقق من الصلاحيات.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق الصارمة.
     */
    public function rules(): array
    {
        // جلب معرف القسم من الرابط (في حالة التعديل) لتجاهله من شرط الـ Unique
        $departmentId = $this->route('department') ? $this->route('department')->id : null;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('departments', 'name')->ignore($departmentId),
            ],
            'description' => 'nullable|string|max:1000'
        ];
    }

    /**
     * رسائل الخطأ المخصصة.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم القسم.',
            'name.min' => 'اسم القسم يجب أن يتكون من حرفين على الأقل.',
            'name.max' => 'اسم القسم طويل جداً (الحد الأقصى 100 حرف).',
            'name.unique' => 'هذا القسم مسجل مسبقاً في النظام، يرجى اختيار اسم آخر.',

            'description.max' => 'وصف القسم طويل جداً (الحد الأقصى 1000 حرف).',
        ];
    }
}

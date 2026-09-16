<?php

namespace App\Http\Requests;

use App\Enums\WorkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UserRequest extends FormRequest
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
        // جلب معرف المستخدم في حالة التعديل لتجاهله من شرط الإيميل والهاتف
        $userId = $this->route('user') ? $this->route('user')->id : null;

        // التحقق مما إذا كانت العملية تعديل (PUT/PATCH)
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'name' => 'required|string|min:3|max:100',

            // التحقق من الدور أولاً
            'role' => 'required|in:manager,employee',

            // المسمى الوظيفي إجباري فقط للموظف، ويجب أن يكون من القائمة
            'job_title' => 'required_if:role,employee|nullable|string|in:Video Editor,Graphic Designer,Developer,Media Buyer,Account Manager,Sales,Manager',

            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'password' => $isUpdate ? 'nullable|string|min:8|max:100' : 'required|string|min:8|max:100',

            'phone' => [
                'required',
                'string',
                'regex:/^\+?[0-9]+$/',
                'min:10',
                'max:15',
                Rule::unique('users', 'phone')->ignore($userId),
            ],

            'work_type' => ['required', new Enum(WorkType::class)],

            'primary_department_id' => 'nullable|exists:departments,id',

            'additional_department_ids' => 'nullable|array',
            'additional_department_ids.*' => 'exists:departments,id',
        ];
    }

    /**
     * رسائل الخطأ المخصصة.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم المستخدم.',
            'name.min' => 'اسم المستخدم يجب أن يتكون من 3 أحرف على الأقل.',

            // رسائل الخطأ الخاصة بالمسمى الوظيفي والدور
            'role.required' => 'يرجى تحديد صلاحية المستخدم (مدير أو موظف).',
            'role.in' => 'الصلاحية المحددة غير صالحة.',
            'job_title.required_if' => 'يرجى اختيار المسمى الوظيفي للموظف.',
            'job_title.in' => 'المسمى الوظيفي المختار غير صحيح. يرجى الاختيار من القائمة المتاحة.',

            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'هذا البريد الإلكتروني مسجل مسبقاً لمستخدم آخر.',

            'password.required' => 'يرجى إدخال كلمة المرور للمستخدم الجديد.',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',

            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط.',
            'phone.min' => 'رقم الهاتف قصير جداً (الحد الأدنى 10 أرقام).',
            'phone.max' => 'رقم الهاتف طويل جداً (الحد الأقصى 15 رقماً).',
            'phone.unique' => 'رقم الهاتف هذا مسجل مسبقاً لمستخدم آخر، يرجى استخدام رقم مختلف.',

            'primary_department_id.exists' => 'القسم الأساسي المحدد غير موجود.',
            'additional_department_ids.*.exists' => 'أحد الأقسام الإضافية المحددة غير موجود في النظام.',
        ];
    }
}
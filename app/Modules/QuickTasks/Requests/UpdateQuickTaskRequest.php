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

        $hasEditPerm = $user->isManager() || $user->hasPermission('quick_tasks.edit');

        // منع الموظف (المنفذ) من التعديل إلا إذا كان يمتلك صلاحية التعديل
        if ($quickTask && (int)$user->id === (int)$quickTask->assigned_to && !$hasEditPerm) {
            return false;
        }

        // السماح للمدير أو لمن يملك الصلاحية أو لمن قام بإنشاء المهمة
        return $hasEditPerm || ($quickTask && (int)$user->id === (int)$quickTask->created_by);
    }

    public function rules()
    {
        return [
            'assigned_to' => 'nullable|integer|exists:users,id',
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'voice_record'=> 'nullable|file', 
            'attachment_file' => 'nullable|file',
            
            // إضافة after:now لمنع اختيار وقت في الماضي عند التعديل
            'deadline'    => 'nullable|date|after:now',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('voice_record')) {
                try {
                    \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::validate($this->file('voice_record'));
                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        foreach ($messages as $msg) {
                            $validator->errors()->add($field, $msg);
                        }
                    }
                }
            }

            if ($this->hasFile('attachment_file')) {
                try {
                    \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::validate($this->file('attachment_file'));
                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        foreach ($messages as $msg) {
                            $validator->errors()->add('attachment_file', $msg);
                        }
                    }
                }
            }
        });
    }

    public function messages()
    {
        return [
            'deadline.after' => 'لا يمكن تعديل الديدلاين لوقت مضى، يجب أن يكون وقت التسليم في المستقبل.',
            'deadline.date'  => 'صيغة التاريخ والوقت غير صحيحة.',
            
            'assigned_to.integer' => 'قيمة الموظف غير صالحة.',
            'assigned_to.exists'  => 'الموظف المحدد غير موجود في قاعدة البيانات.',
            
            'title.max'       => 'عنوان المهمة يجب ألا يتجاوز 255 حرفاً.',
            'description.max' => 'التفاصيل النصية طويلة جداً (الحد الأقصى 5000 حرف).',
        ];
    }
}
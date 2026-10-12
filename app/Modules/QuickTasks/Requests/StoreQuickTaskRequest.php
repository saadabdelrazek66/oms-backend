<?php

namespace App\Modules\QuickTasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuickTaskRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->role->value === 'manager';
    }

    public function rules()
    {
        return [
            // تأكيد أن الحقل مطلوب، رقم صحيح، وموجود بالفعل في جدول المستخدمين
            'assigned_to' => 'required|integer|exists:users,id',
            
            // حماية من النصوص الطويلة جداً
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            
            // إمكانية إرفاق تسجيل صوتي أو ملف مرفق
            'voice_record' => 'nullable|file', 
            'attachment_file' => 'nullable|file',
            
            // التحقق من أن التاريخ صالح وأنه في المستقبل (بعد الوقت الحالي)
            'deadline' => 'required|date|after:now',
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
            'deadline.after' => 'لا يمكن إضافة ديدلاين في وقت مضى، يجب أن يكون وقت التسليم في المستقبل.',
            'deadline.required' => 'تحديد وقت التسليم (الديدلاين) إلزامي.',
            'deadline.date' => 'صيغة التاريخ والوقت غير صحيحة.',
            
            'assigned_to.required' => 'يجب تحديد الموظف المسؤول عن المهمة.',
            'assigned_to.exists' => 'الموظف المحدد غير موجود في قاعدة البيانات.',
            
            'title.max' => 'عنوان المهمة يجب ألا يتجاوز 255 حرفاً.',
            'description.max' => 'التفاصيل النصية طويلة جداً (الحد الأقصى 5000 حرف).',
        ];
    }
}
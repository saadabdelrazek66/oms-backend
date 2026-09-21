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

        // منع الموظف (المنفذ) من التعديل نهائياً إلا إذا كان مديراً
        if ($quickTask && (int)$user->id === (int)$quickTask->assigned_to && $role !== 'manager') {
            return false;
        }

        // السماح للمدير أو لمن قام بإنشاء المهمة
        return $role === 'manager' || ($quickTask && (int)$user->id === (int)$quickTask->created_by);
    }

    public function rules()
    {
        return [
            'assigned_to' => 'nullable|integer|exists:users,id',
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'voice_record'=> 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,ogg,webm,mp4|max:10240', 
            
            // إضافة after:now لمنع اختيار وقت في الماضي عند التعديل
            'deadline'    => 'nullable|date|after:now',
        ];
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
            
            'voice_record.mimes' => 'صيغة الملف الصوتي غير مدعومة، يرجى التسجيل من المتصفح مباشرة أو رفع ملف صوتي صالح.',
            'voice_record.max'   => 'حجم الملف الصوتي يجب ألا يتجاوز 10 ميجابايت.',
        ];
    }
}
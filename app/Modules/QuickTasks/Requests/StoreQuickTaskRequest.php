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
            
            // إضافة mp4 لتغطية بعض متصفحات الموبايل التي تسجل بصيغة فيديو/صوت
            'voice_record' => 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,ogg,webm,mp4|max:10240', 
            
            // التحقق من أن التاريخ صالح وأنه في المستقبل (بعد الوقت الحالي)
            'deadline' => 'required|date|after:now',
        ];
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
            
            'voice_record.mimes' => 'صيغة الملف الصوتي غير مدعومة، يرجى التسجيل من المتصفح مباشرة أو رفع ملف صوتي صالح.',
            'voice_record.max' => 'حجم الملف الصوتي يجب ألا يتجاوز 10 ميجابايت.',
        ];
    }
}
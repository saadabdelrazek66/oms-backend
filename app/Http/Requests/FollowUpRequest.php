<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:2000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'نص المتابعة مطلوب.',
            'image.image' => 'الملف المرفق يجب أن يكون صورة.',
            'image.mimes' => 'صيغة الصورة غير مدعومة (فقط jpeg, png, jpg, gif, webp).',
            'image.max' => 'حجم الصورة كبير جداً (الحد الأقصى 5 ميجابايت).',
        ];
    }
}

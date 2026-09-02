<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientCredentialRequest extends FormRequest
{
    /**
     * التحقق من الصلاحيات.
     * (الباك إند الخاص بالـ Controller يتأكد من الـ PIN، فهنا نكتفي بإرجاع true)
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
        return [
            // المنصة: إجبارية، ولا تتعدى 50 حرفاً (مثل: Facebook, WordPress)
            'platform' => 'required|string|min:2|max:50',

            // الرابط: اختياري، ويجب أن يكون بصيغة URL صحيحة
            'login_url' => 'nullable|url|max:500',

            // اسم المستخدم/الإيميل: إجباري، ولا يتعدى 255 حرفاً
            'username' => 'required|string|max:255',

            // الباسورد: إجبارية، وتم وضع حد أقصى كبير (1000) لدعم الباسوردات المعقدة جداً
            'password' => 'required|string|max:1000',

            // ملاحظات 2FA: اختيارية، وتسمح بنص طويل حتى 1000 حرف
            'two_factor_notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * رسائل الخطأ المخصصة لتجربة مستخدم (UX) ممتازة في الفرونت إند.
     */
    public function messages(): array
    {
        return [
            'platform.required' => 'يرجى إدخال اسم المنصة (مثل: Facebook, Hostinger).',
            'platform.min' => 'اسم المنصة قصير جداً.',
            'platform.max' => 'اسم المنصة يتجاوز الحد الأقصى المسموح به (50 حرف).',

            'login_url.url' => 'رابط تسجيل الدخول غير صالح (تأكد أنه يبدأ بـ http:// أو https://).',
            'login_url.max' => 'الرابط طويل جداً.',

            'username.required' => 'اسم المستخدم أو البريد الإلكتروني مطلوب.',
            'username.max' => 'اسم المستخدم المدخل طويل جداً.',

            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.max' => 'كلمة المرور تتجاوز الحد الأقصى المسموح به.',

            'two_factor_notes.max' => 'ملاحظات المصادقة تتجاوز الحد الأقصى المسموح به (1000 حرف).',
        ];
    }
}

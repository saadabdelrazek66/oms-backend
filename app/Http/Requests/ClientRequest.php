<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:100',
            // فصلنا الـ Regex (للتأكد من أنها أرقام فقط) عن الـ min و max (للتأكد من الطول)
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'min:10', 'max:15'],
            'email' => 'nullable|email:rfc,dns|max:255',
            'bank_account' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]+$/', 'min:10', 'max:34'],
            'instapay' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\.\-]+$/', 'max:50'],
            'wallet' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'min:10', 'max:15'],

            'social_links' => 'nullable|array|max:10',
            'social_links.*' => 'required|url|max:500',

            'contacts' => 'nullable|array|max:10',
            'contacts.*.contact_name' => 'required_with:contacts|string|min:2|max:100',
            'contacts.*.contact_method' => 'required_with:contacts|string|in:phone,email,whatsapp,linkedin,other',
            'contacts.*.contact_details' => 'required_with:contacts|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            // رسائل الاسم
            'name.required' => 'اسم العميل مطلوب.',
            'name.min' => 'اسم العميل يجب أن يتكون من 3 أحرف على الأقل.',
            'name.max' => 'اسم العميل يتجاوز الحد الأقصى للمسميات.',

            // رسائل الهاتف
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط (يسمح بعلامة + في البداية).',
            'phone.min' => 'رقم الهاتف قصير جداً (يجب أن يكون 10 أرقام على الأقل).',
            'phone.max' => 'رقم الهاتف طويل جداً (الحد الأقصى 15 رقماً).',

            // رسائل الإيميل
            'email.email' => 'صيغة البريد الإلكتروني المدخلة غير صحيحة.',

            // رسائل الحساب البنكي
            'bank_account.regex' => 'رقم الحساب البنكي يجب أن يحتوي على حروف إنجليزية وأرقام فقط (بدون مسافات أو رموز).',
            'bank_account.min' => 'رقم الحساب البنكي (IBAN) قصير جداً (الحد الأدنى 10 خانات).',
            'bank_account.max' => 'رقم الحساب البنكي طويل جداً.',

            // رسائل إنستاباي
            'instapay.regex' => 'معرف إنستاباي غير صحيح (يسمح فقط بالحروف الإنجليزية، الأرقام، النقطة، والشرطة).',

            // رسائل المحفظة
            'wallet.regex' => 'رقم المحفظة يجب أن يحتوي على أرقام فقط.',
            'wallet.min' => 'رقم المحفظة قصير جداً (يجب أن يكون 10 أرقام على الأقل).',
            'wallet.max' => 'رقم المحفظة طويل جداً (الحد الأقصى 15 رقماً).',

            // رسائل السوشيال ميديا
            'social_links.max' => 'لا يمكنك إضافة أكثر من 10 روابط للسوشيال ميديا.',
            'social_links.*.url' => 'أحد الروابط المدخلة غير صالح (تأكد أنه يبدأ بـ http:// أو https://).',

            // رسائل جهات الاتصال
            'contacts.max' => 'لا يمكنك إضافة أكثر من 10 جهات اتصال للعميل.',
            'contacts.*.contact_name.required_with' => 'يرجى كتابة اسم جهة الاتصال.',
            'contacts.*.contact_method.in' => 'وسيلة الاتصال المحددة غير مدعومة.',
            'contacts.*.contact_details.required_with' => 'يرجى كتابة تفاصيل وسيلة الاتصال (مثل الرقم أو الرابط).',
        ];
    }
}

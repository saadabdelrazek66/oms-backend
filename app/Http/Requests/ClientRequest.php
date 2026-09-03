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

            // الهواتف (مصفوفة كائنات: الرقم + الواتساب)
            'phones' => 'nullable|array|max:10',
            'phones.*.number' => ['required_with:phones', 'string', 'regex:/^\+?[0-9]+$/', 'min:10', 'max:15'],
            'phones.*.has_whatsapp' => 'nullable|boolean',

            // الإيميلات (مصفوفة نصوص)
            'emails' => 'nullable|array|max:10',
            'emails.*' => 'required_with:emails|email:rfc,dns|max:255',

            // الحسابات البنكية والمالية
            'bank_name' => 'nullable|string|max:150',
            'bank_branch' => 'nullable|string|max:150',
            'bank_account' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]+$/', 'min:10', 'max:34'],

            'instapay' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\.\-]+$/', 'max:50'],
            'wallet' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'min:10', 'max:15'],

            // السوشيال ميديا (مصفوفة كائنات: المنصة + الرابط)
            'social_links' => 'nullable|array|max:15',
            'social_links.*.platform' => 'required_with:social_links|string|max:100',
            'social_links.*.url' => 'required_with:social_links|url|max:500',

            // جهات الاتصال (موجودة في ملفك الأصلي)
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

            // رسائل الهواتف المتعددة
            'phones.max' => 'لا يمكنك إضافة أكثر من 10 أرقام هاتف.',
            'phones.*.number.required_with' => 'يرجى كتابة رقم الهاتف في الحقل المضاف.',
            'phones.*.number.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط (يسمح بعلامة + في البداية).',
            'phones.*.number.min' => 'رقم الهاتف قصير جداً (يجب أن يكون 10 أرقام على الأقل).',
            'phones.*.number.max' => 'رقم الهاتف طويل جداً (الحد الأقصى 15 رقماً).',

            // رسائل الإيميلات المتعددة
            'emails.max' => 'لا يمكنك إضافة أكثر من 10 إيميلات.',
            'emails.*.required_with' => 'يرجى كتابة البريد الإلكتروني في الحقل المضاف.',
            'emails.*.email' => 'صيغة البريد الإلكتروني المدخلة غير صحيحة.',

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

            // رسائل السوشيال ميديا المحدثة
            'social_links.max' => 'لا يمكنك إضافة أكثر من 15 رابطاً للسوشيال ميديا.',
            'social_links.*.platform.required_with' => 'يرجى تحديد اسم المنصة (مثال: فيسبوك، انستجرام).',
            'social_links.*.url.required_with' => 'يرجى إدخال الرابط الخاص بالمنصة.',
            'social_links.*.url.url' => 'أحد الروابط المدخلة غير صالح (تأكد أنه يبدأ بـ http:// أو https://).',

            // رسائل جهات الاتصال
            'contacts.max' => 'لا يمكنك إضافة أكثر من 10 جهات اتصال للعميل.',
            'contacts.*.contact_name.required_with' => 'يرجى كتابة اسم جهة الاتصال.',
            'contacts.*.contact_method.in' => 'وسيلة الاتصال المحددة غير مدعومة.',
            'contacts.*.contact_details.required_with' => 'يرجى كتابة تفاصيل وسيلة الاتصال (مثل الرقم أو الرابط).',
        ];
    }
}

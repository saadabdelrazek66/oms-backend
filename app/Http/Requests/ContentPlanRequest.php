<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('requires_review')) {
            $this->merge([
                'requires_review' => filter_var($this->requires_review, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'plan_type' => 'required|string|min:2|max:100',
            'requires_review' => 'boolean',

            'planned_delivery_date' => 'required|date',
            'planned_review_date' => 'required_if:requires_review,true|nullable|date',

            'responsible_ids' => 'nullable|array',
            'responsible_ids.*' => 'exists:users,id',

            'reviewer_ids' => 'required_if:requires_review,true|array',
            'reviewer_ids.*' => 'exists:users,id',

            'executor_ids' => 'nullable|array',
            'executor_ids.*' => 'exists:users,id',

            'final_link' => 'nullable|url|max:500',
            'notes' => 'nullable|string|max:1000',

            // --- التحديث الجديد ---
            // السماح بمصفوفة روابط بحد أقصى 15 رابط
            'reference_links' => 'nullable|array|max:15',
            // التأكد أن كل عنصر داخل المصفوفة هو رابط حقيقي
            'reference_links.*' => 'required|url|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'يرجى اختيار العميل المستهدف للخطة.',
            'client_id.exists' => 'العميل المحدد غير موجود في النظام.',

            'plan_type.required' => 'نوع الخطة مطلوب.',
            'plan_type.max' => 'نوع الخطة طويل جداً.',

            'planned_delivery_date.required' => 'موعد التسليم النهائي مطلوب.',
            'planned_delivery_date.date' => 'صيغة تاريخ التسليم غير صحيحة.',

            'planned_review_date.required_if' => 'موعد إنهاء المراجعة مطلوب طالما تم تفعيل خيار المراجعة الداخلية.',
            'planned_review_date.date' => 'صيغة تاريخ المراجعة غير صحيحة.',

            'reviewer_ids.required_if' => 'يرجى تحديد مراجع واحد على الأقل طالما تم تفعيل خيار المراجعة الداخلية.',

            'responsible_ids.*.exists' => 'أحد المسؤولين المحددين غير موجود في النظام.',
            'reviewer_ids.*.exists' => 'أحد المراجعين المحددين غير موجود في النظام.',
            'executor_ids.*.exists' => 'أحد المنفذين المحددين غير موجود في النظام.',

            'final_link.url' => 'رابط البلان غير صالح (تأكد أنه يبدأ بـ http:// أو https://).',
            'notes.max' => 'الملاحظات طويلة جداً (الحد الأقصى 1000 حرف).',

            // --- رسائل التحديث الجديد ---
            'reference_links.array' => 'الروابط المرجعية يجب أن تكون قائمة.',
            'reference_links.max' => 'لا يمكنك إضافة أكثر من 15 رابط مرجعي للخطة الواحدة.',
            'reference_links.*.required' => 'رابط المرجع لا يمكن أن يكون فارغاً.',
            'reference_links.*.url' => 'أحد الروابط المرجعية غير صالح (تأكد أنه يبدأ بـ http:// أو https://).',
            'reference_links.*.max' => 'أحد الروابط المرجعية المدخلة طويل جداً.',
        ];
    }
}

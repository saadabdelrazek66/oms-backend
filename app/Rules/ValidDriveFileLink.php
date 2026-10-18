<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDriveFileLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = strtolower($value);

        // فحص إعداد الفحص الصارم لروابط درايف
        try {
            $isStrict = (bool) app(\App\Services\SettingService::class)->get('strict_drive_validation', true);
        } catch (\Throwable $e) {
            $isStrict = true;
        }

        if (!$isStrict) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                $fail('يجب إدخال رابط URL صالح.');
            }
            return;
        }

        if (str_contains($value, '/folders/')) {
            $fail('الرابط المدخل عبارة عن "مجلد" درايف. يجب إدخال رابط "الملف" المرفوع من داخل المجلد.');
            return;
        }

        $isValidFile = str_contains($value, 'drive.google.com/file/d/') 
                    || str_contains($value, 'drive.google.com/open?id=') 
                    || str_contains($value, 'docs.google.com/document/d/') 
                    || str_contains($value, 'docs.google.com/spreadsheets/d/')
                    || str_contains($value, 'docs.google.com/presentation/d/');

        if (!$isValidFile) {
            $fail('يجب أن يكون الرابط رابطاً صحيحاً لملف على Google Drive أو Google Docs/Sheets.');
        }
    }
}

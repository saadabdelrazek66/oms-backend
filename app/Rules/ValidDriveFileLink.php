<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDriveFileLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = strtolower($value);

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
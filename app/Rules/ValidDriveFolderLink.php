<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDriveFolderLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $value = strtolower(trim((string) $value));

        // التحقق من أنه رابط مجلد صالح على Google Drive
        $isDriveFolder = (str_contains($value, 'drive.google.com') && str_contains($value, '/folders/'))
                      || (str_contains($value, 'drive.google.com/open?id=') && !str_contains($value, '/file/d/'));

        if (!$isDriveFolder) {
            $fail('يجب أن يكون الرابط عبارة عن رابط مجلد (Folder) صحيح على Google Drive لأرشفة المنشور.');
        }
    }
}

<?php

namespace App\Modules\QuickTasks\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuickTaskAttachmentSecurity
{
    /**
     * الحد الأقصى لحجم الملف المسموح به بالبايت (25 ميجابايت)
     */
    public const MAX_FILE_SIZE_BYTES = 26214400; // 25 MB
    public const MAX_FILE_SIZE_MB = 25;

    /**
     * القائمة البيضاء للصيغ المسموح برفعها
     */
    public const ALLOWED_EXTENSIONS = [
        // المستندات
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf',
        // الصور
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'ico',
        // الملفات الصوتية
        'mp3', 'wav', 'ogg', 'm4a', 'webm', 'aac', 'flac', 'wma', 'amr',
        // ملفات الفيديو
        'mp4', 'mov', 'avi', 'mkv', 'wmv',
        // الملفات المضغوطة
        'zip', 'rar', '7z', 'tar', 'gz',
        // ملفات التصميم والبيانات
        'psd', 'ai', 'xd', 'fig', 'sketch', 'json', 'xml'
    ];

    /**
     * القائمة السوداء للملفات البرمجية والتنفيذية الخطرة (فحص صارم ضد الامتدادات المزدوجة والحيل)
     */
    public const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps', 'pht',
        'exe', 'bat', 'cmd', 'sh', 'bash', 'bin', 'com', 'cpl', 'dll', 'jar', 'msi', 'scr', 'vbs',
        'js', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'pyc', 'pyo', 'pyd',
        'htaccess', 'htpasswd', 'ini', 'env', 'config', 'command'
    ];

    /**
     * قائمة أنواع MIME المحظورة قطعياً
     */
    public const BLOCKED_MIME_TYPES = [
        'application/x-httpd-php',
        'application/x-php',
        'text/x-php',
        'text/php',
        'application/x-executable',
        'application/x-msdownload',
        'application/x-sh',
        'text/x-shellscript',
        'application/javascript',
        'text/javascript',
        'text/x-python',
        'application/x-python-code',
    ];

    /**
     * فحص أمني شامل وصارم للملف المرفوع
     *
     * @param UploadedFile $file
     * @throws ValidationException
     */
    public static function validate(UploadedFile $file): void
    {
        // 1. التحقق من سلامة الملف الأساسية
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'voice_record' => ['فشل رفع الملف أو أن الملف تالف أثناء الإرسال.']
            ]);
        }

        // 2. التحقق من حجم الملف
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'voice_record' => [
                    'حجم الملف يتجاوز الحد الأقصى المسموح به (' . self::MAX_FILE_SIZE_MB . ' ميجابايت).'
                ]
            ]);
        }

        $originalName = $file->getClientOriginalName();

        // 3. الحماية من تسمم البايت الصفري (Null-byte Injection)
        if (strpos($originalName, "\0") !== false) {
            throw ValidationException::withMessages([
                'voice_record' => ['اسم الملف يحتوي على محارف غير مسموح بها.']
            ]);
        }

        // 4. الحماية من الامتدادات المزدوجة والملفات المتخفية (Double Extensions e.g. shell.php.png)
        $nameParts = explode('.', strtolower($originalName));
        if (count($nameParts) > 1) {
            // فحص جميع أجزاء الاسم بحثاً عن أي امتداد خطير
            foreach ($nameParts as $part) {
                if (in_array($part, self::BLOCKED_EXTENSIONS, true)) {
                    throw ValidationException::withMessages([
                        'voice_record' => ['نوع الملف غير آمن ومحظور رفعه نهائياً لأسباب أمنية.']
                    ]);
                }
            }
        }

        // 5. التحقق من الامتداد النهائي مقابل القائمة البيضاء
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'voice_record' => [
                    "صيغة الملف (.{$extension}) غير مدعومة. الصيغ المسموحة تشمل: المستندات (PDF, Word, Excel)، الصور، الصوتيات، الفيديوهات، والملفات المضغوطة (ZIP, RAR)."
                ]
            ]);
        }

        // 6. التحقق من نوع MIME الحقيقي للملف (Magic Bytes)
        $mimeType = strtolower($file->getMimeType() ?: '');
        if (in_array($mimeType, self::BLOCKED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'voice_record' => ['محتوى الملف غير آمن ومحظور رفعه.']
            ]);
        }

        // 7. حماية خاصة لملفات SVG ضد XSS (فحص وجود وسوم البرمجة النصية)
        if ($extension === 'svg' || str_contains($mimeType, 'svg')) {
            $content = @file_get_contents($file->getRealPath());
            if ($content !== false) {
                $dangerousPatterns = [
                    '/<script/i',
                    '/javascript\s*:/i',
                    '/onload\s*=/i',
                    '/onerror\s*=/i',
                    '/onclick\s*=/i',
                    '/<iframe/i',
                    '/<object/i',
                    '/<embed/i'
                ];
                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        throw ValidationException::withMessages([
                            'voice_record' => ['ملف SVG يحتوي على وسوم برمجية غير آمنة ومحظور رفعه.']
                        ]);
                    }
                }
            }
        }
    }

    /**
     * حفظ الملف المرفوع باسم عشوائي آمن ومسار محمي
     *
     * @param UploadedFile $file
     * @param string $prefix
     * @return string المسار النسبي للملف
     */
    public static function storeSafely(UploadedFile $file, string $prefix = 'attachment'): string
    {
        self::validate($file);

        // إنشاء المجلد وتأمينه
        $targetDir = public_path('uploads/quick_tasks/attachments');
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true, true);
        }

        // تأمين مجلد uploads بالكامل بملف .htaccess يمنع تشغيل ملفات PHP
        self::ensureUploadsDirectoryProtection();

        // توليد اسم عشوائي آمن غير مشتق من اسم الملف الأصلي
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $randomFileName = time() . "_{$prefix}_" . Str::random(24) . '.' . $extension;

        $file->move($targetDir, $randomFileName);

        return 'uploads/quick_tasks/attachments/' . $randomFileName;
    }

    /**
     * وضع ملف .htaccess داخل مجلد uploads لمنع تشغيل أي سكربتات PHP حتى لو تم رفعها
     */
    public static function ensureUploadsDirectoryProtection(): void
    {
        $htaccessPath = public_path('uploads/.htaccess');
        if (!File::exists($htaccessPath)) {
            $content = <<<'HTACCESS'
# حماية أمنية مشددة لمجلد الرفع: منع تنفيذ أي ملفات برمجية
<FilesMatch "\.(?i:php|php3|php4|php5|php7|php8|phtml|phar|phps|pht|pl|py|cgi|asp|aspx|sh|bash|exe|command)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>
<IfModule mod_php.c>
    php_flag engine off
</IfModule>

RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py .asp
Options -ExecCGI
HTACCESS;
            @File::put($htaccessPath, $content);
        }
    }

    /**
     * تصنيف نوع المرفق بناءً على الامتداد
     */
    public static function classifyType(?string $path): string
    {
        if (!$path) return 'none';

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'webm', 'aac', 'flac', 'wma', 'amr'];
        $imageExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'ico'];
        $videoExts = ['mp4', 'mov', 'avi', 'mkv', 'wmv'];
        $archiveExts = ['zip', 'rar', '7z', 'tar', 'gz'];
        $docExts = ['doc', 'docx', 'txt', 'rtf'];
        $sheetExts = ['xls', 'xlsx', 'csv'];
        $slideExts = ['ppt', 'pptx'];

        if (in_array($ext, $audioExts, true)) return 'audio';
        if (in_array($ext, $imageExts, true)) return 'image';
        if (in_array($ext, $videoExts, true)) return 'video';
        if ($ext === 'pdf') return 'pdf';
        if (in_array($ext, $docExts, true)) return 'document';
        if (in_array($ext, $sheetExts, true)) return 'spreadsheet';
        if (in_array($ext, $slideExts, true)) return 'presentation';
        if (in_array($ext, $archiveExts, true)) return 'archive';

        return 'file';
    }

    /**
     * استخراج حجم الملف المرفوع بشكل منسق مقروء
     */
    public static function formatFileSize(?string $path): ?string
    {
        if (!$path) return null;
        $fullPath = public_path($path);
        if (!File::exists($fullPath)) return null;

        $bytes = @filesize($fullPath);
        if ($bytes === false) return null;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

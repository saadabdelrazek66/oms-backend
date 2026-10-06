<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPlatformLink implements ValidationRule
{
    protected ?string $platform;

    public function __construct(?string $platform = null)
    {
        $this->platform = $platform;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $result = self::validateLink($this->platform ?? $attribute, (string) $value);
        if (!$result['valid']) {
            $fail($result['message']);
        }
    }

    /**
     * التحقق من أن الرابط صالح وينتمي للمنصة المحددة
     */
    public static function validateLink(?string $platform, string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'message' => 'الرابط مطلوب.'];
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if (!$host || !str_contains($host, '.')) {
            return ['valid' => false, 'message' => 'صيغة الرابط غير صحيحة، يرجى إدخال رابط إنترنت صالح (URL).'];
        }

        $norm = self::normalizePlatform($platform);
        $detected = self::detectPlatformFromUrl($url);

        $platformDomainMap = [
            'facebook' => ['facebook.com', 'fb.com', 'fb.watch'],
            'instagram' => ['instagram.com', 'instagr.am'],
            'twitter' => ['twitter.com', 'x.com', 't.co'],
            'linkedin' => ['linkedin.com', 'lnkd.in'],
            'tiktok' => ['tiktok.com'],
            'snapchat' => ['snapchat.com', 'snap.com'],
            'youtube' => ['youtube.com', 'youtu.be'],
            'threads' => ['threads.net', 'threads.com'],
            'pinterest' => ['pinterest.com', 'pin.it'],
            'telegram' => ['t.me', 'telegram.me', 'telegram.org'],
        ];

        if (!isset($platformDomainMap[$norm])) {
            return ['valid' => true, 'message' => ''];
        }

        $allowedDomains = $platformDomainMap[$norm];
        $isMatch = false;
        foreach ($allowedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                $isMatch = true;
                break;
            }
        }

        if ($isMatch) {
            return ['valid' => true, 'message' => ''];
        }

        if ($detected) {
            return [
                'valid' => false,
                'message' => "الرابط المدخل لمنصة ({$platform}) يخص منصة ({$detected}) وليس ({$platform})! يرجى إدخال رابط يخص {$platform}."
            ];
        }

        $example = $allowedDomains[0];
        return [
            'valid' => false,
            'message' => "رابط غير صالح لمنصة {$platform}! يجب أن ينتمي الرابط لنطاق المنصة (مثل: {$example})."
        ];
    }

    public static function normalizePlatform(?string $platform): string
    {
        if (!$platform) return '';
        $p = mb_strtolower(trim($platform), 'UTF-8');
        if (str_contains($p, 'facebook') || str_contains($p, 'فيسبوك') || $p === 'fb') return 'facebook';
        if (str_contains($p, 'instagram') || str_contains($p, 'انستق') || str_contains($p, 'إنستغ') || str_contains($p, 'انستج') || $p === 'ig') return 'instagram';
        if (str_contains($p, 'twitter') || str_contains($p, 'تويتر') || $p === 'x' || str_contains($p, 'twitter/x') || str_contains($p, 'منصة x') || str_contains($p, 'منصة إكس')) return 'twitter';
        if (str_contains($p, 'linkedin') || str_contains($p, 'لينكد') || str_contains($p, 'لينكدان')) return 'linkedin';
        if (str_contains($p, 'tiktok') || str_contains($p, 'تيك') || str_contains($p, 'توك')) return 'tiktok';
        if (str_contains($p, 'snapchat') || str_contains($p, 'سناب')) return 'snapchat';
        if (str_contains($p, 'youtube') || str_contains($p, 'يوتيوب')) return 'youtube';
        if (str_contains($p, 'threads') || str_contains($p, 'ثريدز')) return 'threads';
        if (str_contains($p, 'pinterest') || str_contains($p, 'بينترست')) return 'pinterest';
        if (str_contains($p, 'telegram') || str_contains($p, 'تليجرام') || str_contains($p, 'تيليجرام')) return 'telegram';
        return $p;
    }

    public static function detectPlatformFromUrl(string $url): ?string
    {
        $u = mb_strtolower(trim($url), 'UTF-8');
        if (str_contains($u, 'facebook.com') || str_contains($u, 'fb.com') || str_contains($u, 'fb.watch')) return 'Facebook';
        if (str_contains($u, 'instagram.com') || str_contains($u, 'instagr.am')) return 'Instagram';
        if (str_contains($u, 'twitter.com') || str_contains($u, 'x.com') || str_contains($u, 't.co/')) return 'Twitter/X';
        if (str_contains($u, 'linkedin.com') || str_contains($u, 'lnkd.in')) return 'LinkedIn';
        if (str_contains($u, 'tiktok.com')) return 'TikTok';
        if (str_contains($u, 'snapchat.com') || str_contains($u, 'snap.com')) return 'Snapchat';
        if (str_contains($u, 'youtube.com') || str_contains($u, 'youtu.be')) return 'YouTube';
        if (str_contains($u, 'threads.net') || str_contains($u, 'threads.com')) return 'Threads';
        if (str_contains($u, 'pinterest.com') || str_contains($u, 'pin.it')) return 'Pinterest';
        if (str_contains($u, 't.me') || str_contains($u, 'telegram.me') || str_contains($u, 'telegram.org')) return 'Telegram';
        if (str_contains($u, 'drive.google.com') || str_contains($u, 'docs.google.com')) return 'Google Drive';
        return null;
    }
}

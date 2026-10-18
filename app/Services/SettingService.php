<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    const CACHE_KEY = 'oms_system_settings_all';
    const CACHE_TTL_SECONDS = 86400; // 24 hours

    /**
     * Get all cached settings key-value map
     */
    public static function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $settings = SystemSetting::all();
            $map = [];
            foreach ($settings as $s) {
                $map[$s->key] = $s->casted_value;
            }
            return $map;
        });
    }

    /**
     * Get a single setting value
     */
    public static function get(string $key, $default = null)
    {
        $all = self::getAll();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Set a batch of settings
     */
    public static function setBatch(array $values): void
    {
        foreach ($values as $key => $val) {
            $setting = SystemSetting::where('key', $key)->first();
            if ($setting) {
                $setting->value = SystemSetting::serializeValue($val, $setting->type);
                $setting->save();
            }
        }
        self::clearCache();
    }

    /**
     * Clear settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

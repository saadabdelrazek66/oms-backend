<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\SettingService;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    /**
     * Get all settings grouped by module for manager
     */
    public function index()
    {
        $settings = SystemSetting::orderBy('sort_order')->get();

        $groupLabels = [
            'general' => [
                'label' => 'الهوية والبيانات العامة',
                'icon' => '🏢',
                'description' => 'بيانات الوكالة، العملة الافتراضية، المنطقة الزمنية وبداية الأسبوع',
            ],
            'workload' => [
                'label' => 'سياسات الدوام وحِمل العمل',
                'icon' => '⏱️',
                'description' => 'ساعات العمل، مواعيد الدوام، أيام العطلة ومحرك الترحيل التلقائي',
            ],
            'plans' => [
                'label' => 'خطط المحتوى وشيت المنشورات',
                'icon' => '📊',
                'description' => 'قواعد تسليم البريف، التكرار التلقائي، واعتمادات الأقسام',
            ],
            'notifications' => [
                'label' => 'التنبيهات والواتساب',
                'icon' => '🔔',
                'description' => 'فترات تنبيهات الديدلاين، قوالب الواتساب، والمؤثرات الصوتية',
            ],
            'security' => [
                'label' => 'الأمان وسلة المهملات',
                'icon' => '🔒',
                'description' => 'صلاحية الجلسات، مهلة سلة المهملات، ووضع تجميد النظام',
            ],
            'storage' => [
                'label' => 'التخزين والأرشفة السحابية',
                'icon' => '☁️',
                'description' => 'روابط جوجل درايف الرئيسية وسياسات الأرشفة السنوية',
            ],
        ];

        $grouped = [];
        foreach ($settings->groupBy('group') as $groupKey => $items) {
            $meta = $groupLabels[$groupKey] ?? [
                'label' => ucfirst($groupKey),
                'icon' => '⚙️',
                'description' => '',
            ];

            $grouped[] = [
                'group' => $groupKey,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'description' => $meta['description'],
                'settings' => $items->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'key' => $s->key,
                        'value' => $s->casted_value,
                        'type' => $s->type,
                        'name_ar' => $s->name_ar,
                        'description_ar' => $s->description_ar,
                        'options' => $s->options,
                    ];
                }),
            ];
        }

        return response()->json([
            'groups' => $grouped,
            'flat' => SettingService::getAll(),
        ]);
    }

    /**
     * Batch update settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        $settings = $request->input('settings');
        SettingService::setBatch($settings);

        activity('Settings')
            ->causedBy($request->user())
            ->log('تم تحديث إعدادات النظام العامة بنجاح.');

        return response()->json([
            'message' => 'تم حفظ إعدادات النظام بنجاح 🚀',
            'flat' => SettingService::getAll(),
        ]);
    }

    /**
     * Reset a group to default values
     */
    public function resetGroup(Request $request)
    {
        $request->validate([
            'group' => 'required|string',
        ]);

        $seeder = new \Database\Seeders\SystemSettingSeeder();
        // Run specific group reset if needed or re-seed
        $seeder->run();
        SettingService::clearCache();

        activity('Settings')
            ->causedBy($request->user())
            ->log("تم استعادة الإعدادات الافتراضية لقسم: {$request->group}");

        return response()->json([
            'message' => 'تم استعادة القيم الافتراضية بنجاح.',
            'flat' => SettingService::getAll(),
        ]);
    }

    /**
     * Public settings for any authenticated user (Agency name, currency, timezone, weekend days)
     */
    public function publicSettings()
    {
        $all = SettingService::getAll();
        return response()->json([
            'agency_name' => $all['agency_name'] ?? 'Octo Media',
            'agency_tagline' => $all['agency_tagline'] ?? '',
            'default_currency' => $all['default_currency'] ?? 'EGP',
            'system_timezone' => $all['system_timezone'] ?? 'Africa/Cairo',
            'weekend_days' => $all['weekend_days'] ?? ['friday'],
            'sound_effects_enabled' => (bool) ($all['sound_effects_enabled'] ?? true),
            'system_freeze_mode' => (bool) ($all['system_freeze_mode'] ?? false),
            'default_daily_hours' => (float) ($all['default_daily_hours'] ?? 8.0),
            'overload_threshold_percentage' => (int) ($all['overload_threshold_percentage'] ?? 100),
            'root_drive_archive_folder' => $all['root_drive_archive_folder'] ?? '',
            'strict_drive_validation' => (bool) ($all['strict_drive_validation'] ?? true),
            'auto_lock_on_approval' => (bool) ($all['auto_lock_on_approval'] ?? true),
            'week_start_day' => $all['week_start_day'] ?? 'saturday',
        ]);
    }
}

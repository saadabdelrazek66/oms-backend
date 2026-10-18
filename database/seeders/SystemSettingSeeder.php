<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ==========================================
            // 🏢 1. الهوية والبيانات العامة (General)
            // ==========================================
            [
                'key' => 'agency_name',
                'value' => 'Octo Media',
                'group' => 'general',
                'type' => 'string',
                'name_ar' => 'اسم الوكالة / المنظومة',
                'description_ar' => 'الاسم الرسمي للشركة أو الوكالة الذي يظهر في شريط التنقل والتقارير والرسائل.',
                'options' => null,
                'sort_order' => 10,
            ],
            [
                'key' => 'agency_tagline',
                'value' => 'مساحة العمل الإبداعية لإدارة المحتوى والإنتاج الرقمي',
                'group' => 'general',
                'type' => 'string',
                'name_ar' => 'الشعار الترويجي (Tagline)',
                'description_ar' => 'الجملة الوصفية أو الشعار الذي يظهر أسفل اسم الوكالة في القائمة الجانبية.',
                'options' => null,
                'sort_order' => 11,
            ],
            [
                'key' => 'official_email',
                'value' => 'contact@octomedia.com',
                'group' => 'general',
                'type' => 'string',
                'name_ar' => 'البريد الإلكتروني الرسمي',
                'description_ar' => 'البريد الإلكتروني المعتمد للمراسلات والدعم الفني للنظام.',
                'options' => null,
                'sort_order' => 12,
            ],
            [
                'key' => 'official_phone',
                'value' => '+201000000000',
                'group' => 'general',
                'type' => 'string',
                'name_ar' => 'رقم الهاتف / الدعم',
                'description_ar' => 'رقم الاتصال المعتمد للوكالة.',
                'options' => null,
                'sort_order' => 13,
            ],
            [
                'key' => 'default_currency',
                'value' => 'EGP',
                'group' => 'general',
                'type' => 'string',
                'name_ar' => 'العملة الافتراضية للمنظومة',
                'description_ar' => 'العملة المعتمدة لحساب موازنات التمويل الإعلاني وتكاليف البوستات.',
                'options' => [
                    ['label' => 'جنيه مصري (EGP)', 'value' => 'EGP'],
                    ['label' => 'دولار أمريكي (USD)', 'value' => 'USD'],
                    ['label' => 'ريال سعودي (SAR)', 'value' => 'SAR'],
                    ['label' => 'درهم إماراتي (AED)', 'value' => 'AED'],
                ],
                'sort_order' => 14,
            ],
            [
                'key' => 'system_timezone',
                'value' => 'Africa/Cairo',
                'group' => 'general',
                'type' => 'string',
                'name_ar' => 'المنطقة الزمنية (Timezone)',
                'description_ar' => 'المنطقة الزمنية المعتمدة لاحتساب المواعيد وتواريخ التسليم وسجلات النشاط.',
                'options' => [
                    ['label' => 'القاهرة (Africa/Cairo - UTC+2 / +3)', 'value' => 'Africa/Cairo'],
                    ['label' => 'الرياض (Asia/Riyadh - UTC+3)', 'value' => 'Asia/Riyadh'],
                    ['label' => 'دبي (Asia/Dubai - UTC+4)', 'value' => 'Asia/Dubai'],
                    ['label' => 'التوقيت العالمي (UTC)', 'value' => 'UTC'],
                ],
                'sort_order' => 15,
            ],
            [
                'key' => 'week_start_day',
                'value' => 'saturday',
                'group' => 'general',
                'type' => 'string',
                'name_ar' => 'أول أيام أسبوع العمل',
                'description_ar' => 'اليوم الذي يبدأ به أسبوع العمل في الشريط الزمني ولوحة التخطيط.',
                'options' => [
                    ['label' => 'السبت', 'value' => 'saturday'],
                    ['label' => 'الأحد', 'value' => 'sunday'],
                    ['label' => 'الإثنين', 'value' => 'monday'],
                ],
                'sort_order' => 16,
            ],

            // ==========================================
            // ⏱️ 2. سياسات الدوام وحِمل العمل (Workload)
            // ==========================================
            [
                'key' => 'default_daily_hours',
                'value' => '8',
                'group' => 'workload',
                'type' => 'number',
                'name_ar' => 'ساعات العمل اليومية الافتراضية',
                'description_ar' => 'عدد ساعات العمل اليومية القياسية لأي موظف جديد عند إنشائه.',
                'options' => null,
                'sort_order' => 20,
            ],
            [
                'key' => 'office_start_time',
                'value' => '09:00',
                'group' => 'workload',
                'type' => 'string',
                'name_ar' => 'وقت بداية الدوام الرسمي',
                'description_ar' => 'الموعد الرسمي لبدء العمل في مقر الشركة.',
                'options' => null,
                'sort_order' => 21,
            ],
            [
                'key' => 'office_end_time',
                'value' => '17:00',
                'group' => 'workload',
                'type' => 'string',
                'name_ar' => 'وقت نهاية الدوام الرسمي',
                'description_ar' => 'الموعد الرسمي لانتهاء ساعات العمل.',
                'options' => null,
                'sort_order' => 22,
            ],
            [
                'key' => 'weekend_days',
                'value' => json_encode(['friday']),
                'group' => 'workload',
                'type' => 'array',
                'name_ar' => 'أيام العطلة الأسبوعية للشركة',
                'description_ar' => 'الأيام التي تُعتبر إجازة رسمية ولا يتم إسناد ديدلاين فيها تلقائياً.',
                'options' => [
                    ['label' => 'الجمعة', 'value' => 'friday'],
                    ['label' => 'السبت', 'value' => 'saturday'],
                    ['label' => 'الأحد', 'value' => 'sunday'],
                ],
                'sort_order' => 23,
            ],
            [
                'key' => 'overload_threshold_percentage',
                'value' => '100',
                'group' => 'workload',
                'type' => 'number',
                'name_ar' => 'نسبة التنبيه من ضغط العمل (%)',
                'description_ar' => 'النسبة المئوية لساعات العمل التي يتم عندها تلوين عمود الموظف بالأحمر في لوحة اليوم.',
                'options' => null,
                'sort_order' => 24,
            ],
            [
                'key' => 'auto_cascade_enabled',
                'value' => '1',
                'group' => 'workload',
                'type' => 'boolean',
                'name_ar' => 'تفعيل محرك الترحيل التلقائي للمهام (Auto-Cascading)',
                'description_ar' => 'ترحيل المهام والبوستات تلقائياً لليوم التالي عند ضغط الساعات أو تسجيل مهام طارئة.',
                'options' => null,
                'sort_order' => 25,
            ],
            [
                'key' => 'late_delivery_grace_minutes',
                'value' => '60',
                'group' => 'workload',
                'type' => 'number',
                'name_ar' => 'مهلة السماح قبل احتساب التأخير (بالدقائق)',
                'description_ar' => 'فترة السماح بعد انقضاء الديدلاين قبل تمييز المهمة كمتأخرة رسمياً.',
                'options' => null,
                'sort_order' => 26,
            ],

            // ==========================================
            // 📊 3. خطط المحتوى وشيت المنشورات (Plans)
            // ==========================================
            [
                'key' => 'min_brief_lead_hours',
                'value' => '48',
                'group' => 'plans',
                'type' => 'number',
                'name_ar' => 'المهلة الإلزامية لتسليم الـ Brief (بالساعات)',
                'description_ar' => 'الحد الأدنى لفرق الوقت بين ميعاد تسليم البريف وتاريخ النشر المخطط.',
                'options' => null,
                'sort_order' => 30,
            ],
            [
                'key' => 'default_auto_recurrence',
                'value' => '0',
                'group' => 'plans',
                'type' => 'boolean',
                'name_ar' => 'تفعيل التكرار التلقائي الافتراضي للخطط',
                'description_ar' => 'تحديد ما إذا كانت الخطط الجديدة تُنشأ كخطط متكررة شهرياً بشكل افتراضي.',
                'options' => null,
                'sort_order' => 31,
            ],
            [
                'key' => 'require_dept_review_first',
                'value' => '1',
                'group' => 'plans',
                'type' => 'boolean',
                'name_ar' => 'إلزامية اعتماد رئيس القسم قبل المدير',
                'description_ar' => 'منع انتقال المنشور للاعتماد النهائي من المدير إلا بعد موافقة رئيس القسم أولاً.',
                'options' => null,
                'sort_order' => 32,
            ],
            [
                'key' => 'strict_drive_validation',
                'value' => '1',
                'group' => 'plans',
                'type' => 'boolean',
                'name_ar' => 'التحقق الصارم من روابط جوجل درايف ودوكس',
                'description_ar' => 'التأكد الصارم من أن الروابط تتبع Google Drive / Docs مع السماح بالمجلدات للكاروسيل فقط.',
                'options' => null,
                'sort_order' => 33,
            ],
            [
                'key' => 'auto_lock_on_approval',
                'value' => '1',
                'group' => 'plans',
                'type' => 'boolean',
                'name_ar' => 'قفل الصفوف والحقول تلقائياً بعد الاعتماد',
                'description_ar' => 'قفل منشورات الشيت تلقائياً بعد اعتمادها من المدير لحمايتها من أي تعديل غير مقصود.',
                'options' => null,
                'sort_order' => 34,
            ],

            // ==========================================
            // 🔔 4. التنبيهات والواتساب (Notifications)
            // ==========================================
            [
                'key' => 'deadline_alert_intervals',
                'value' => json_encode([24, 12, 2]),
                'group' => 'notifications',
                'type' => 'array',
                'name_ar' => 'فترات تنبيهات اقتراب الديدلاين (بالساعات)',
                'description_ar' => 'إرسال تنبيهات تلقائية للمنفذين قبل حلول موعد التسليم.',
                'options' => null,
                'sort_order' => 40,
            ],
            [
                'key' => 'sound_effects_enabled',
                'value' => '1',
                'group' => 'notifications',
                'type' => 'boolean',
                'name_ar' => 'تفعيل المؤثرات الصوتية للتنبيهات',
                'description_ar' => 'تشغيل نغمة تنبيه لطيفة وفاخرة عند وصول إشعار جديد أو اعتماد مهمة.',
                'options' => null,
                'sort_order' => 41,
            ],
            [
                'key' => 'whatsapp_notifications_enabled',
                'value' => '1',
                'group' => 'notifications',
                'type' => 'boolean',
                'name_ar' => 'تفعيل إنشاء رسائل الواتساب التلقائية',
                'description_ar' => 'تجهيز نصوص الواتساب التلقائية الجاهزة للإرسال عند الاعتماد أو التكليف.',
                'options' => null,
                'sort_order' => 42,
            ],
            [
                'key' => 'admin_whatsapp_number',
                'value' => '+201000000000',
                'group' => 'notifications',
                'type' => 'string',
                'name_ar' => 'رقم واتساب الإدارة المعتمد',
                'description_ar' => 'الرقم المخصص لاستقبال الإشعارات والتقارير الإدارية الملخصة.',
                'options' => null,
                'sort_order' => 43,
            ],
            [
                'key' => 'whatsapp_custom_footer',
                'value' => "مع تحيات فريق إدارة Octo Media 🐙\nشركة رائدة في صناعة المحتوى والإعلان",
                'group' => 'notifications',
                'type' => 'string',
                'name_ar' => 'تذييل رسائل الواتساب (WhatsApp Footer)',
                'description_ar' => 'النص الترحيبي أو التوقيع الذي يُضاف في نهاية رسائل الواتساب التلقائية.',
                'options' => null,
                'sort_order' => 44,
            ],

            // ==========================================
            // 🔒 5. الأمان والاحتفاظ بالبيانات (Security)
            // ==========================================
            [
                'key' => 'session_lifetime_hours',
                'value' => '24',
                'group' => 'security',
                'type' => 'number',
                'name_ar' => 'مدة بقاء جلسة تسجيل الدخول (بالساعات)',
                'description_ar' => 'المدة الزمنية التي يظل فيها حساب المستخدم مسجلاً للدخول قبل المطالبة بإعادة الدخول.',
                'options' => [
                    ['label' => '12 ساعة', 'value' => 12],
                    ['label' => '24 ساعة (يوم كامل)', 'value' => 24],
                    ['label' => '7 أيام (أسبوع)', 'value' => 168],
                    ['label' => '30 يوماً (شهر)', 'value' => 720],
                ],
                'sort_order' => 50,
            ],
            [
                'key' => 'trash_retention_days',
                'value' => '30',
                'group' => 'security',
                'type' => 'number',
                'name_ar' => 'مهلة سلة المهملات قبل الحذف التلقائي (بالأيام)',
                'description_ar' => 'الفترة الزمنية التي تُحفظ فيها العناصر المحذوفة في السلة قبل تفريغها (ضع 0 للحفظ الدائم).',
                'options' => null,
                'sort_order' => 51,
            ],
            [
                'key' => 'audit_log_retention_days',
                'value' => '180',
                'group' => 'security',
                'type' => 'number',
                'name_ar' => 'فترة الاحتفاظ بسجل النشاطات (بالأيام)',
                'description_ar' => 'مدة حفظ السجلات والـ Audit Trail في النظام للرجوع إليها.',
                'options' => null,
                'sort_order' => 52,
            ],
            [
                'key' => 'system_freeze_mode',
                'value' => '0',
                'group' => 'security',
                'type' => 'boolean',
                'name_ar' => 'وضع تجميد النظام والصيانة (System Freeze)',
                'description_ar' => 'عند تفعيله، يتم تحويل النظام لوضع القراءة فقط للموظفين لمنع أي إنشاء أو تعديل مؤقتاً.',
                'options' => null,
                'sort_order' => 53,
            ],

            // ==========================================
            // ☁️ 6. التخزين والأرشفة السحابية (Storage)
            // ==========================================
            [
                'key' => 'root_drive_archive_folder',
                'value' => '',
                'group' => 'storage',
                'type' => 'string',
                'name_ar' => 'رابط مجلد الأرشيف الرئيسي على Google Drive',
                'description_ar' => 'رابط المجلد الرئيسي السحابي الذي تُحفظ فيه ملفات ومجلدات جميع العملاء والأرشيف العام.',
                'options' => null,
                'sort_order' => 60,
            ],
            [
                'key' => 'archive_instructions_ar',
                'value' => "1. يتم إنشاء مجلد باسم العميل داخل مجلد الأرشيف الرئيسي.\n2. داخل مجلد العميل، يتم تقسيم العمل إلى مجلد لكل سنة وشهر.\n3. يجب التأكد من ضبط صلاحيات المشاركة لتكون متاحة لروابط العمل المعتمدة.",
                'group' => 'storage',
                'type' => 'string',
                'name_ar' => 'إرشادات وسياسات الأرشفة للفريق',
                'description_ar' => 'التعليمات التوجيهية التي تظهر للموظفين عند تسليم وأرشفة ملفات العملاء.',
                'options' => null,
                'sort_order' => 61,
            ],
        ];

        foreach ($settings as $item) {
            SystemSetting::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }
}

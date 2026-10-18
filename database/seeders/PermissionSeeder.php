<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;


class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // --- 1. العملاء والشركات (Clients) ---
            [
                'code' => 'clients.view',
                'module' => 'clients',
                'action' => 'view',
                'name_ar' => 'عرض العملاء والشركات',
                'description_ar' => 'رؤية صفحة العملاء والشركات وتفاصيلهم',
                'sort_order' => 10,
            ],
            [
                'code' => 'clients.create',
                'module' => 'clients',
                'action' => 'create',
                'name_ar' => 'إضافة عميل جديد',
                'description_ar' => 'إمكانية تسجيل عميل أو شركة جديدة في النظام',
                'sort_order' => 11,
            ],
            [
                'code' => 'clients.edit',
                'module' => 'clients',
                'action' => 'edit',
                'name_ar' => 'تعديل بيانات العميل',
                'description_ar' => 'تعديل بيانات العميل، أرقام التواصل، والروابط',
                'sort_order' => 12,
            ],
            [
                'code' => 'clients.delete',
                'module' => 'clients',
                'action' => 'delete',
                'name_ar' => 'حذف عميل',
                'description_ar' => 'حذف العميل ونقله لسلة المهملات',
                'sort_order' => 13,
            ],

            // --- 2. خزنة العملاء (Client Vault) ---
            [
                'code' => 'vault.view',
                'module' => 'vault',
                'action' => 'view',
                'name_ar' => 'عرض خزنة العملاء',
                'description_ar' => 'الوصول لخزنة كلمات المرور وبيانات دخول الحسابات',
                'sort_order' => 20,
            ],
            [
                'code' => 'vault.manage',
                'module' => 'vault',
                'action' => 'manage',
                'name_ar' => 'إدارة حسابات الخزنة',
                'description_ar' => 'إضافة وتعديل وحذف بيانات الدخول السرية للعملاء',
                'sort_order' => 21,
            ],

            // --- 3. المشاريع (Projects) ---
            [
                'code' => 'projects.view',
                'module' => 'projects',
                'action' => 'view',
                'name_ar' => 'عرض المشاريع',
                'description_ar' => 'استعراض صفحة المشاريع وتفاصيلها',
                'sort_order' => 30,
            ],
            [
                'code' => 'projects.create',
                'module' => 'projects',
                'action' => 'create',
                'name_ar' => 'إنشاء مشروع جديد',
                'description_ar' => 'إضافة مشروع جديد وتعيين بياناته وتاريخ التسليم',
                'sort_order' => 31,
            ],
            [
                'code' => 'projects.edit',
                'module' => 'projects',
                'action' => 'edit',
                'name_ar' => 'تعديل المشروع',
                'description_ar' => 'تعديل تفاصيل المشروع والمسؤولين والمواعيد',
                'sort_order' => 32,
            ],
            [
                'code' => 'projects.delete',
                'module' => 'projects',
                'action' => 'delete',
                'name_ar' => 'حذف مشروع',
                'description_ar' => 'حذف المشروع ومهامه إلى سلة المهملات',
                'sort_order' => 33,
            ],

            // --- 4. المهام وتوزيع العمل (Tasks) ---
            [
                'code' => 'tasks.view',
                'module' => 'tasks',
                'action' => 'view',
                'name_ar' => 'عرض المهام',
                'description_ar' => 'استعراض المهام المسندة ومهام المشاريع',
                'sort_order' => 40,
            ],
            [
                'code' => 'tasks.create',
                'module' => 'tasks',
                'action' => 'create',
                'name_ar' => 'إنشاء مهام جديدة',
                'description_ar' => 'إضافة مهمة داخل المشاريع وتحديد ساعاتها المقدرة',
                'sort_order' => 41,
            ],
            [
                'code' => 'tasks.edit',
                'module' => 'tasks',
                'action' => 'edit',
                'name_ar' => 'تعديل المهام',
                'description_ar' => 'تعديل بيانات وتفاصيل المهام والمواعيد',
                'sort_order' => 42,
            ],
            [
                'code' => 'tasks.delete',
                'module' => 'tasks',
                'action' => 'delete',
                'name_ar' => 'حذف مهمة',
                'description_ar' => 'حذف المهام من المشاريع',
                'sort_order' => 43,
            ],
            [
                'code' => 'tasks.assign',
                'module' => 'tasks',
                'action' => 'assign',
                'name_ar' => 'إسناد وتكليف المهام',
                'description_ar' => 'إسناد المهمة لموظفين آخرين وتغيير المسؤولين',
                'sort_order' => 44,
            ],
            [
                'code' => 'tasks.approve',
                'module' => 'tasks',
                'action' => 'approve',
                'name_ar' => 'اعتماد وقبول المهمة',
                'description_ar' => 'الموافقة على تسليم المهمة وتحويلها لمنجزة',
                'sort_order' => 45,
            ],
            [
                'code' => 'tasks.reject',
                'module' => 'tasks',
                'action' => 'reject',
                'name_ar' => 'رفض المهمة وإعادتها',
                'description_ar' => 'رفض تسليم المهمة مع إضافة سبب الرفض للتعديل',
                'sort_order' => 46,
            ],

            // --- 5. خطط المحتوى (Content Plans) ---
            [
                'code' => 'plans.view',
                'module' => 'plans',
                'action' => 'view',
                'name_ar' => 'عرض خطط المحتوى',
                'description_ar' => 'رؤية صفحة إدارة الخطط الشهرية وبطاقاتها',
                'sort_order' => 50,
            ],
            [
                'code' => 'plans.create',
                'module' => 'plans',
                'action' => 'create',
                'name_ar' => 'إنشاء خطة محتوى',
                'description_ar' => 'إنشاء خطة شهرية جديدة للعميل وتحديد مخرجاتها',
                'sort_order' => 51,
            ],
            [
                'code' => 'plans.edit',
                'module' => 'plans',
                'action' => 'edit',
                'name_ar' => 'تعديل خطة المحتوى',
                'description_ar' => 'تعديل تفاصيل الخطة، المخرجات، ومسؤول الخطة',
                'sort_order' => 52,
            ],
            [
                'code' => 'plans.delete',
                'module' => 'plans',
                'action' => 'delete',
                'name_ar' => 'حذف خطة المحتوى',
                'description_ar' => 'حذف الخطة ونقلها لسلة المهملات',
                'sort_order' => 53,
            ],
            [
                'code' => 'plans.duplicate',
                'module' => 'plans',
                'action' => 'create',
                'name_ar' => 'تكرار ومضاعفة الخطة',
                'description_ar' => 'تكرار خطة حالية لشهر جديد بنفس المخرجات',
                'sort_order' => 54,
            ],
            [
                'code' => 'plans.approve',
                'module' => 'plans',
                'action' => 'approve',
                'name_ar' => 'اعتماد خطة المحتوى',
                'description_ar' => 'اعتماد الخطة وإرسالها أو تفعيلها رسمياً',
                'sort_order' => 55,
            ],
            [
                'code' => 'plans.reject',
                'module' => 'plans',
                'action' => 'reject',
                'name_ar' => 'رفض خطة المحتوى',
                'description_ar' => 'رفض مسودة الخطة لإعادة صياغتها',
                'sort_order' => 56,
            ],

            // --- 6. شيت وجداول المنشورات (Spreadsheet Posts) ---
            [
                'code' => 'posts.view',
                'module' => 'posts',
                'action' => 'view',
                'name_ar' => 'عرض جداول المنشورات',
                'description_ar' => 'الدخول لشيت المنشورات واستعراض صفوف الخطة',
                'sort_order' => 60,
            ],
            [
                'code' => 'posts.create',
                'module' => 'posts',
                'action' => 'create',
                'name_ar' => 'إضافة صفوف ومنشورات جديدة',
                'description_ar' => 'إضافة بوست جديد في شيت الخطة',
                'sort_order' => 61,
            ],
            [
                'code' => 'posts.edit_brief',
                'module' => 'posts',
                'action' => 'edit',
                'name_ar' => 'كتابة وتعديل الـ Brief والمحتوى',
                'description_ar' => 'تعديل الفكرة، الكابشن، الأهداف، والتاريخ المستهدف',
                'sort_order' => 62,
            ],
            [
                'code' => 'posts.edit_media',
                'module' => 'posts',
                'action' => 'edit',
                'name_ar' => 'تسليم وتعديل التصاميم والميديا',
                'description_ar' => 'إرفاق روابط تسليم البوستات ومجلدات العمل للمراجعة',
                'sort_order' => 63,
            ],
            [
                'code' => 'posts.edit_ads',
                'module' => 'posts',
                'action' => 'edit',
                'name_ar' => 'إدارة التمويل والإعلانات',
                'description_ar' => 'تعديل منصات النشر وموقف وتكلفة التمويل الإعلاني',
                'sort_order' => 64,
            ],
            [
                'code' => 'posts.dept_approve',
                'module' => 'posts',
                'action' => 'approve',
                'name_ar' => 'اعتماد رئيس القسم للمنشور',
                'description_ar' => 'اعتماد البوست بعد المراجعة وتأكيده للعميل',
                'sort_order' => 65,
            ],
            [
                'code' => 'posts.dept_reject',
                'module' => 'posts',
                'action' => 'reject',
                'name_ar' => 'رفض رئيس القسم للمنشور',
                'description_ar' => 'رفض البوست مع ذكر أسباب التعديل للمنفذ',
                'sort_order' => 66,
            ],
            [
                'code' => 'posts.final_delivery',
                'module' => 'posts',
                'action' => 'edit',
                'name_ar' => 'التسليم النهائي للأرشفة',
                'description_ar' => 'إرفاق رابط مجلد درايف للتسليم النهائي بعد الاعتماد',
                'sort_order' => 67,
            ],
            [
                'code' => 'posts.delete',
                'module' => 'posts',
                'action' => 'delete',
                'name_ar' => 'حذف منشور من الشيت',
                'description_ar' => 'حذف صف المنشور من جدول الخطة',
                'sort_order' => 68,
            ],

            // --- 7. المهام السريعة (Quick Tasks) ---
            [
                'code' => 'quick_tasks.view',
                'module' => 'quick_tasks',
                'action' => 'view',
                'name_ar' => 'عرض لوحة المهام السريعة',
                'description_ar' => 'استعراض لوحة المهام السريعة والطارئة',
                'sort_order' => 70,
            ],
            [
                'code' => 'quick_tasks.create',
                'module' => 'quick_tasks',
                'action' => 'create',
                'name_ar' => 'إنشاء مهمة سريعة جديدة',
                'description_ar' => 'إضافة تاسك سريع وتكليف الزملاء به',
                'sort_order' => 71,
            ],
            [
                'code' => 'quick_tasks.edit',
                'module' => 'quick_tasks',
                'action' => 'edit',
                'name_ar' => 'تعديل المهمة السريعة',
                'description_ar' => 'تعديل تفاصيل المهمة والملفات المرفقة',
                'sort_order' => 72,
            ],
            [
                'code' => 'quick_tasks.delete',
                'module' => 'quick_tasks',
                'action' => 'delete',
                'name_ar' => 'حذف مهمة سريعة',
                'description_ar' => 'حذف المهمة السريعة نهائياً',
                'sort_order' => 73,
            ],
            [
                'code' => 'quick_tasks.review',
                'module' => 'quick_tasks',
                'action' => 'approve',
                'name_ar' => 'مراجعة واعتماد/رفض المهمة السريعة',
                'description_ar' => 'قبول تسليم التاسك السريع أو رفضه وطلب تعديلات',
                'sort_order' => 74,
            ],

            // --- 8. لوحة اليوم وحمل العمل (Workload) ---
            [
                'code' => 'workload.view',
                'module' => 'workload',
                'action' => 'view',
                'name_ar' => 'عرض الشريط الزمني ولوحة اليوم',
                'description_ar' => 'استعراض لوحة اليوم والطاقة الاستيعابية للفريق',
                'sort_order' => 80,
            ],
            [
                'code' => 'workload.manage',
                'module' => 'workload',
                'action' => 'manage',
                'name_ar' => 'إدارة وترحيل ساعات العمل',
                'description_ar' => 'التحكم بالترحيل التلقائي للمهام وتوزيع الساعات',
                'sort_order' => 81,
            ],

            // --- 9. إدارة الأقسام (Departments) ---
            [
                'code' => 'departments.view',
                'module' => 'departments',
                'action' => 'view',
                'name_ar' => 'عرض الأقسام',
                'description_ar' => 'استعراض قائمة الأقسام والهيكل التنظيمي',
                'sort_order' => 90,
            ],
            [
                'code' => 'departments.manage',
                'module' => 'departments',
                'action' => 'manage',
                'name_ar' => 'إدارة وإضافة وتعديل الأقسام',
                'description_ar' => 'إنشاء أقسام جديدة وتعديل الأقسام وحذفها',
                'sort_order' => 91,
            ],

            // --- 10. سجل النظام والمحفوظات (System Logs & Trash) ---
            [
                'code' => 'logs.view',
                'module' => 'logs',
                'action' => 'view',
                'name_ar' => 'عرض سجل النظام والنشاطات',
                'description_ar' => 'استعراض الحركات المسجلة والـ Audit Trail',
                'sort_order' => 100,
            ],
            [
                'code' => 'trash.view',
                'module' => 'trash',
                'action' => 'view',
                'name_ar' => 'عرض سلة المهملات',
                'description_ar' => 'استعراض العناصر والخطط المحذوفة',
                'sort_order' => 101,
            ],
            [
                'code' => 'trash.restore',
                'module' => 'trash',
                'action' => 'manage',
                'name_ar' => 'استرجاع العناصر المحذوفة',
                'description_ar' => 'استعادة العناصر المحذوفة من سلة المهملات',
                'sort_order' => 102,
            ],
            [
                'code' => 'trash.force_delete',
                'module' => 'trash',
                'action' => 'delete',
                'name_ar' => 'الحذف النهائي وتفريغ السلة',
                'description_ar' => 'الحذف النهائي الدائم للعناصر بدون إمكانية الاسترجاع',
                'sort_order' => 103,
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['code' => $perm['code']],
                $perm
            );
        }
    }
}

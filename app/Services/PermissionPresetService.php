<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class PermissionPresetService
{
    /**
     * Get all predefined permission presets
     */
    public static function getPresets(): array
    {
        return [
            'account_manager' => [
                'name_ar' => 'مدير حسابات (Account Manager)',
                'description_ar' => 'صلاحيات كاملة للعملاء والخطط ومتابعة المهام والشيت والمراجعات',
                'permissions' => [
                    'clients.view', 'clients.create', 'clients.edit',
                    'plans.view', 'plans.create', 'plans.edit', 'plans.duplicate', 'plans.approve', 'plans.reject',
                    'posts.view', 'posts.create', 'posts.edit_brief', 'posts.dept_approve', 'posts.dept_reject', 'posts.final_delivery',
                    'projects.view', 'projects.create', 'projects.edit',
                    'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.assign', 'tasks.approve', 'tasks.reject',
                    'quick_tasks.view', 'quick_tasks.create', 'quick_tasks.edit', 'quick_tasks.review',
                    'workload.view',
                    'departments.view',
                ],
            ],
            'graphic_designer' => [
                'name_ar' => 'مصمم جرافيك (Graphic Designer)',
                'description_ar' => 'رؤية المنشورات والشيت ورفع روابط التصاميم والمهام الموكلة إليه',
                'permissions' => [
                    'posts.view', 'posts.edit_media',
                    'projects.view',
                    'tasks.view',
                    'quick_tasks.view', 'quick_tasks.create',
                    'workload.view',
                ],
            ],
            'video_editor' => [
                'name_ar' => 'مونتير وفيديو (Video Editor)',
                'description_ar' => 'رؤية المنشورات والشيت ورفع ملفات الفيديو والمونتاج والمهام الموكلة إليه',
                'permissions' => [
                    'posts.view', 'posts.edit_media',
                    'projects.view',
                    'tasks.view',
                    'quick_tasks.view', 'quick_tasks.create',
                    'workload.view',
                ],
            ],
            'content_writer' => [
                'name_ar' => 'كاتب محتوى (Content Writer / Copywriter)',
                'description_ar' => 'استعراض الخطط، إنشاء المنشورات وتعديل الـ Brief والكابشن والأفكار',
                'permissions' => [
                    'plans.view',
                    'posts.view', 'posts.create', 'posts.edit_brief',
                    'projects.view',
                    'tasks.view',
                    'quick_tasks.view', 'quick_tasks.create',
                    'workload.view',
                ],
            ],
            'media_buyer' => [
                'name_ar' => 'مسوق إعلاني (Media Buyer)',
                'description_ar' => 'استعراض العملاء، تعديل منصات النشر وموقف وتكلفة التمويل الإعلاني في الشيت',
                'permissions' => [
                    'clients.view',
                    'posts.view', 'posts.edit_ads',
                    'projects.view',
                    'tasks.view',
                    'workload.view',
                ],
            ],
            'team_leader' => [
                'name_ar' => 'مشرف وقائد فريق (Team Leader / Head)',
                'description_ar' => 'إدارة المهام وتكليفها واعتماد ورفض تسليمات الفريق والمنشورات',
                'permissions' => [
                    'clients.view',
                    'plans.view',
                    'posts.view', 'posts.create', 'posts.edit_brief', 'posts.edit_media', 'posts.dept_approve', 'posts.dept_reject',
                    'projects.view', 'projects.create', 'projects.edit',
                    'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.assign', 'tasks.approve', 'tasks.reject',
                    'quick_tasks.view', 'quick_tasks.create', 'quick_tasks.edit', 'quick_tasks.review',
                    'workload.view', 'workload.manage',
                    'departments.view',
                ],
            ],
            'full_employee' => [
                'name_ar' => 'صلاحيات شاملة للموظف (All Employee Features)',
                'description_ar' => 'الوصول لكافة ميزات النظام المتاحة للموظفين',
                'permissions' => [
                    'clients.view', 'clients.create', 'clients.edit',
                    'plans.view', 'plans.create', 'plans.edit', 'plans.duplicate', 'plans.approve', 'plans.reject',
                    'posts.view', 'posts.create', 'posts.edit_brief', 'posts.edit_media', 'posts.edit_ads', 'posts.dept_approve', 'posts.dept_reject', 'posts.final_delivery',
                    'projects.view', 'projects.create', 'projects.edit',
                    'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.assign', 'tasks.approve', 'tasks.reject',
                    'quick_tasks.view', 'quick_tasks.create', 'quick_tasks.edit', 'quick_tasks.review',
                    'workload.view', 'workload.manage',
                    'departments.view',
                ],
            ],
        ];
    }

    /**
     * Map job title to default permission codes if no custom permissions are set
     */
    public static function getDefaultPermissionsForUser(User $user): array
    {
        $jobTitle = strtolower(trim((string)$user->job_title));
        $presets = self::getPresets();

        if (str_contains($jobTitle, 'account') || str_contains($jobTitle, 'sales')) {
            return $presets['account_manager']['permissions'];
        }
        if (str_contains($jobTitle, 'video') || str_contains($jobTitle, 'editor') || str_contains($jobTitle, 'motion')) {
            return $presets['video_editor']['permissions'];
        }
        if (str_contains($jobTitle, 'designer') || str_contains($jobTitle, 'design') || str_contains($jobTitle, 'graphic')) {
            return $presets['graphic_designer']['permissions'];
        }
        if (str_contains($jobTitle, 'copy') || str_contains($jobTitle, 'content') || str_contains($jobTitle, 'writer')) {
            return $presets['content_writer']['permissions'];
        }
        if (str_contains($jobTitle, 'media') || str_contains($jobTitle, 'buyer') || str_contains($jobTitle, 'ads')) {
            return $presets['media_buyer']['permissions'];
        }
        if (str_contains($jobTitle, 'lead') || str_contains($jobTitle, 'head') || str_contains($jobTitle, 'supervisor')) {
            return $presets['team_leader']['permissions'];
        }

        // Generic default for standard employee
        return [
            'projects.view',
            'tasks.view',
            'quick_tasks.view', 'quick_tasks.create',
            'workload.view',
        ];
    }
}

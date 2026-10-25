<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Services\PermissionPresetService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Get all system permissions grouped by module
     */
    public function index()
    {
        $permissions = Permission::orderBy('sort_order')->get();

        // تجميع الصلاحيات حسب الموديول
        $grouped = $permissions->groupBy('module');

        $moduleLabels = [
            'clients' => 'العملاء والشركات',
            'vault' => 'خزنة العملاء والبيانات السرية',
            'projects' => 'المشاريع',
            'tasks' => 'المهام وتوزيع العمل',
            'plans' => 'خطط المحتوى الشهرية',
            'posts' => 'شيت وجداول المنشورات',
            'quick_tasks' => 'المهام السريعة',
            'workload' => 'لوحة اليوم وحمل العمل',
            'departments' => 'إدارة الأقسام',
            'users' => 'المستخدمين وفريق العمل',
            'logs' => 'سجل النظام والنشاطات',
            'trash' => 'سلة المهملات والمحفوظات',
        ];

        $result = [];
        foreach ($grouped as $module => $items) {
            $result[] = [
                'module' => $module,
                'module_label' => $moduleLabels[$module] ?? ucfirst($module),
                'permissions' => $items,
            ];
        }

        return response()->json([
            'modules' => $result,
            'presets' => PermissionPresetService::getPresets(),
        ]);
    }

    /**
     * Get specific user permissions
     */
    public function getUserPermissions(User $user)
    {
        $isCustom = (bool) $user->has_custom_permissions;
        $assignedCodes = $user->permissions()->pluck('code')->toArray();
        $activeCodes = $isCustom ? $assignedCodes : PermissionPresetService::getDefaultPermissionsForUser($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'role' => $user->role,
                'is_custom' => $isCustom,
            ],
            'permission_codes' => $activeCodes,
        ]);
    }

    /**
     * Update/Sync user permissions
     */
    public function updateUserPermissions(Request $request, User $user)
    {
        $request->validate([
            'permission_codes' => 'present|array',
            'permission_codes.*' => 'string|exists:permissions,code',
        ]);

        $permissionIds = Permission::whereIn('code', $request->permission_codes)->pluck('id')->toArray();
        $user->permissions()->sync($permissionIds);
        $user->has_custom_permissions = true;
        $user->save();

        activity('Permissions')
            ->causedBy($request->user())
            ->performedOn($user)
            ->log("تم تحديث صلاحيات الموظف {$user->name} بنجاح.");

        return response()->json([
            'message' => 'تم تحديث صلاحيات الموظف بنجاح.',
            'permission_codes' => $user->fresh()->permissions()->pluck('code')->toArray(),
        ]);
    }

    /**
     * Apply a preset directly to a user
     */
    public function applyPreset(Request $request, User $user)
    {
        $request->validate([
            'preset_key' => 'required|string',
        ]);

        $presets = PermissionPresetService::getPresets();
        if (!isset($presets[$request->preset_key])) {
            return response()->json(['message' => 'القالب المحدد غير موجود.'], 404);
        }

        $codes = $presets[$request->preset_key]['permissions'];
        $permissionIds = Permission::whereIn('code', $codes)->pluck('id')->toArray();
        $user->permissions()->sync($permissionIds);
        $user->has_custom_permissions = true;
        $user->save();

        activity('Permissions')
            ->causedBy($request->user())
            ->performedOn($user)
            ->log("تم تطبيق قالب ({$presets[$request->preset_key]['name_ar']}) للموظف {$user->name}.");

        return response()->json([
            'message' => 'تم تطبيق القالب بنجاح.',
            'permission_codes' => $codes,
        ]);
    }
}

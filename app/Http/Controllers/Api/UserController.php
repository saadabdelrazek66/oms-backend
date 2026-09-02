<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Http\Requests\UserRequest;

class UserController extends Controller
{
    public function index()
    {
        // جلب المستخدمين مع أقسامهم
        $users = User::with('departments')->orderBy('id', 'desc')->paginate(15);
        return response()->json($users);
    }

    // إضافة مستخدم جديد
    public function store(UserRequest $request)
    {
        $validated = $request->validated();

        // تشفير كلمة المرور قبل الحفظ
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // مزامنة الأقسام
        $this->syncUserDepartments($user, $request);

        return response()->json([
            'message' => 'تم إضافة المستخدم بنجاح',
            'data' => $user->load('departments')
        ], 201);
    }

    // تعديل بيانات مستخدم
    public function update(UserRequest $request, User $user)
    {
        $validated = $request->validated();

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        $this->syncUserDepartments($user, $request);

        return response()->json([
            'message' => 'تم تعديل المستخدم بنجاح',
            'data' => $user->load('departments')
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // دالة مساعدة لربط الموظف بالقسم الأساسي والأقسام الإضافية
    private function syncUserDepartments(User $user, Request $request)
    {
        $syncData = [];

        // إضافة القسم الأساسي (is_primary = true)
        if ($request->filled('primary_department_id')) {
            $syncData[$request->primary_department_id] = ['is_primary' => true];
        }

        // إضافة الأقسام الإضافية (is_primary = false)
        if ($request->filled('additional_department_ids')) {
            foreach ($request->additional_department_ids as $deptId) {
                // التأكد من عدم تكرار القسم الأساسي كقسم إضافي
                if ($deptId != $request->primary_department_id) {
                    $syncData[$deptId] = ['is_primary' => false];
                }
            }
        }

        // تنفيذ عملية الربط في قاعدة البيانات
        $user->departments()->sync($syncData);
    }
}

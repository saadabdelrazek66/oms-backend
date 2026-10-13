<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Http\Requests\UserRequest;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(5, (int)$request->input('per_page', 15)));
        $users = User::with('departments:id,name')->orderBy('id', 'desc')->paginate($perPage);
        return response()->json($users);
    }

    // إضافة مستخدم جديد
    public function store(UserRequest $request)
    {
        $validated = $request->validated();

        // حساب واستنتاج ساعات العمل تلقائياً
        $this->computeDailyWorkHours($validated);

        // استنتاج وتحديد قائمة أيام العمل تلقائياً
        $this->computeWorkingDays($validated);

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

        // حساب واستنتاج ساعات العمل تلقائياً
        $this->computeDailyWorkHours($validated);

        // استنتاج وتحديد قائمة أيام العمل تلقائياً
        $this->computeWorkingDays($validated);

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

    /**
     * استنتاج وحساب عدد ساعات العمل اليومية تلقائياً من وقت البدء والانتهاء
     */
    private function computeDailyWorkHours(array &$validated): void
    {
        if (!empty($validated['work_start_time']) && !empty($validated['work_end_time'])) {
            try {
                $startStr = substr($validated['work_start_time'], 0, 5);
                $endStr = substr($validated['work_end_time'], 0, 5);
                $start = Carbon::createFromFormat('H:i', $startStr);
                $end = Carbon::createFromFormat('H:i', $endStr);

                if ($end->lessThanOrEqualTo($start)) {
                    // دوام عابر لمنتصف الليل (مثال: 22:00 إلى 06:00)
                    $diffMinutes = (1440 - ($start->hour * 60 + $start->minute)) + ($end->hour * 60 + $end->minute);
                } else {
                    $diffMinutes = $start->diffInMinutes($end);
                }
                $validated['daily_work_hours'] = round($diffMinutes / 60, 2);
            } catch (\Exception $e) {
                if (empty($validated['daily_work_hours'])) {
                    $validated['daily_work_hours'] = 8.0;
                }
            }
        } elseif (empty($validated['daily_work_hours'])) {
            $validated['daily_work_hours'] = 8.0;
        }
    }

    /**
     * استنتاج وتحديد قائمة أيام العمل بناءً على يوم البداية والنهاية
     */
    private function computeWorkingDays(array &$validated): void
    {
        $allDays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        $startDay = strtolower($validated['work_start_day'] ?? 'saturday');
        $endDay = strtolower($validated['work_end_day'] ?? 'thursday');

        if (!in_array($startDay, $allDays)) $startDay = 'saturday';
        if (!in_array($endDay, $allDays)) $endDay = 'thursday';

        $startIndex = array_search($startDay, $allDays);
        $endIndex = array_search($endDay, $allDays);

        $computedDays = [];
        $idx = $startIndex;
        while (true) {
            $computedDays[] = $allDays[$idx];
            if ($idx === $endIndex) {
                break;
            }
            $idx = ($idx + 1) % 7;
        }

        $validated['work_start_day'] = $startDay;
        $validated['work_end_day'] = $endDay;
        $validated['working_days'] = $computedDays;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkloadCascadingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WorkloadController extends Controller
{
    protected WorkloadCascadingService $workloadService;

    public function __construct(WorkloadCascadingService $workloadService)
    {
        $this->workloadService = $workloadService;
    }

    /**
     * جلب سعة العمل اليومية لموظف محدد
     */
    public function getDailyCapacity(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'date' => 'nullable|date',
        ]);

        $currentUser = auth()->user();
        $targetUserId = $request->input('user_id');

        if (!$targetUserId || $currentUser->role->value !== 'manager') {
            $targetUserId = $currentUser->id;
        }

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $schedule = $this->workloadService->getEmployeeDaySchedule((int) $targetUserId, $date->toDateString());

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * استرجاع بيانات لوحة اليوم التفاعلية (الشريط الزمني الأفقي)
     */
    public function getTodayBoard(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'date' => 'nullable|date',
        ]);

        $currentUser = auth()->user();
        $isManager = $currentUser && (
            $currentUser->role === \App\Enums\Role::MANAGER ||
            (is_object($currentUser->role) && $currentUser->role->value === 'manager') ||
            $currentUser->role === 'manager'
        );

        $targetUserId = $request->input('user_id');

        // إذا لم يكن مديراً، يتم إجباره على رؤية مهامه هو فقط
        if (!$isManager || !$targetUserId) {
            $targetUserId = $currentUser->id;
        }

        $dateString = $request->input('date') ? Carbon::parse($request->input('date'))->toDateString() : Carbon::today()->toDateString();

        $data = $this->workloadService->getTodayTimelineBoard((int) $targetUserId, $dateString, $isManager);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * جلب سعة أسبوع عمل كامل (السبت إلى الخميس)
     */
    public function getWeekCapacity(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
        ]);

        $currentUser = auth()->user();
        $targetUserId = $request->input('user_id');

        if (!$targetUserId || $currentUser->role->value !== 'manager') {
            $targetUserId = $currentUser->id;
        }

        $targetUser = \App\Models\User::find($targetUserId);
        $start = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::today();
        
        $days = [];
        $curr = $start->copy();
        $targetWorkingDaysCount = ($targetUser && is_array($targetUser->working_days) && count($targetUser->working_days) > 0)
            ? count($targetUser->working_days)
            : 6;

        $safety = 0;
        while (count($days) < $targetWorkingDaysCount && $safety < 14) {
            $safety++;
            $isWorking = $targetUser ? $targetUser->isWorkingDay($curr) : !$curr->isFriday();
            if ($isWorking) {
                $days[] = $this->workloadService->getEmployeeDaySchedule((int) $targetUserId, $curr->toDateString());
            }
            $curr->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $days,
        ]);
    }

    /**
     * تشغيل معالج الترحيل المتتالي يدوياً أو بعد إدراج عاجل
     */
    public function triggerAutoCascade(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'urgent_id' => 'required',
            'urgent_type' => 'required|in:plan_post,task,quick_task',
            'urgent_title' => 'required|string',
        ]);

        $userId = (int) $request->input('user_id');
        $startDate = Carbon::parse($request->input('date'));
        $urgentId = $request->input('urgent_id');
        $urgentTitle = $request->input('urgent_title');
        $urgentType = $request->input('urgent_type');

        $result = $this->workloadService->autoCascadeWorkload($userId, $startDate, $urgentId, $urgentTitle, $urgentType);
        $displaced = isset($result['displaced_items']) ? $result['displaced_items'] : (is_array($result) ? $result : []);
        $warning = $result['warning'] ?? null;

        return response()->json([
            'success' => true,
            'message' => $warning ? $warning : (count($displaced) > 0 
                ? 'تمت إعادة ضبط المواعيد والترحيل المتتالي بنجاح لضمان عدم تجاوز سعة العمل اليومية.'
                : 'جدول اليوم متزن ولا يتطلب أي إزاحة.'),
            'warning' => $warning,
            'displaced_count' => count($displaced),
            'displaced_items' => $displaced,
        ]);
    }

    /**
     * تشغيل معالج الإرجاع الذكي للمهام المرحّلة عند انتهاء أو إلغاء حالة الطوارئ
     */
    public function triggerAutoRollback(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'urgent_id' => 'nullable',
            'urgent_title' => 'nullable|string',
            'urgent_type' => 'nullable|in:plan_post,task,quick_task',
        ]);

        $userId = (int) $request->input('user_id');
        $urgentId = $request->input('urgent_id');
        $urgentTitle = $request->input('urgent_title');
        $urgentType = $request->input('urgent_type');

        $result = $this->workloadService->autoRollbackWorkload($userId, $urgentId, $urgentTitle, $urgentType);
        $rolledBack = $result['rolled_back_items'] ?? [];

        return response()->json([
            'success' => true,
            'message' => count($rolledBack) > 0
                ? 'تم بنجاح إرجاع ' . count($rolledBack) . ' من المهام المرحّلة إلى مواعيدها الأصلية بعد زوال حالة الطوارئ.'
                : 'لا توجد مهام مرحلة بحاجة للإرجاع أو أن السعة لا تسمح حالياً.',
            'rolled_back_count' => count($rolledBack),
            'rolled_back_items' => $rolledBack,
        ]);
    }

    /**
     * تشفيت عدد محدد من الساعات من مهمة معينة يدويًا
     */
    public function shiftHours(Request $request)
    {
        $request->validate([
            'task_type' => 'required|in:plan_post,task,quick_task',
            'task_id' => 'required|integer',
            'hours' => 'required|numeric|min:0.25|max:24',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'user_id' => 'nullable|exists:users,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $currentUser = auth()->user();
        $targetUserId = $request->input('user_id');
        if (!$targetUserId || $currentUser->role->value !== 'manager') {
            $targetUserId = $currentUser->id;
        }

        try {
            $result = $this->workloadService->shiftTaskHours(
                (int) $targetUserId,
                $request->input('task_type'),
                (int) $request->input('task_id'),
                (float) $request->input('hours'),
                $request->input('from_date'),
                $request->input('to_date'),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => "تم بنجاح تشفيت {$result['shifted_hours']} ساعة إلى تاريخ {$result['to_date']} ⏱️",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * استرجاع الساعات المشفتة لمهمة معينة
     */
    public function restoreShiftedHours(Request $request)
    {
        $request->validate([
            'task_type' => 'required|in:plan_post,task,quick_task',
            'task_id' => 'required|integer',
            'target_date' => 'required|date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $currentUser = auth()->user();
        $targetUserId = $request->input('user_id');
        if (!$targetUserId || $currentUser->role->value !== 'manager') {
            $targetUserId = $currentUser->id;
        }

        try {
            $result = $this->workloadService->restoreShiftedHours(
                (int) $targetUserId,
                $request->input('task_type'),
                (int) $request->input('task_id'),
                $request->input('target_date')
            );

            return response()->json([
                'success' => true,
                'message' => "تم بنجاح استرجاع الساعات المشفتة ({$result['restored_hours']} ساعة) ↩️",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

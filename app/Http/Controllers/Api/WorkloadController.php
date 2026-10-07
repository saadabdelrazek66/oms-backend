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

        $start = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::today();
        
        // جلب 6 أيام عمل متتالية مع تخطي الجمعة
        $days = [];
        $curr = $start->copy();

        for ($i = 0; $i < 6; $i++) {
            if ($curr->isFriday()) {
                $curr->addDay(); // تخطي الجمعة
            }
            $days[] = $this->workloadService->getEmployeeDaySchedule((int) $targetUserId, $curr->toDateString());
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
            'urgent_type' => 'required|in:plan_post,task',
            'urgent_title' => 'required|string',
        ]);

        $userId = (int) $request->input('user_id');
        $startDate = Carbon::parse($request->input('date'));
        $urgentId = $request->input('urgent_id');
        $urgentTitle = $request->input('urgent_title');

        $result = $this->workloadService->autoCascadeWorkload($userId, $startDate, $urgentId, $urgentTitle);
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
        ]);

        $userId = (int) $request->input('user_id');
        $urgentId = $request->input('urgent_id');
        $urgentTitle = $request->input('urgent_title');

        $result = $this->workloadService->autoRollbackWorkload($userId, $urgentId, $urgentTitle);
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
}

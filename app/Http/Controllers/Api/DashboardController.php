<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Task;
use App\Models\ContentPlan;
use App\Models\PlanPost;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * جلب بيانات داشبورد الموظف بطريقة محسنة ومفصلة للمهام والمنشورات
     */
    public function employeeDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // 1. إحصائيات مهام المشاريع (Tasks Stats)
        $taskStats = Task::where('assigned_to', $user->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->selectRaw('SUM(CASE WHEN status IN ("todo", "in_progress", "in_review") THEN 1 ELSE 0 END) as pending')
            ->selectRaw('SUM(CASE WHEN status != "completed" AND due_date < ? THEN 1 ELSE 0 END) as overdue', [$now])
            ->first();

        // 2. إحصائيات منشورات الخطط (Posts Stats) - الجديد!
        $postStats = PlanPost::where(function($q) use ($user) {
            // جلب المنشورات التي يشارك فيها كمنفذ أو مراجع
            $q->where('designer_id', $user->id)
                ->orWhereJsonContains('reviewer_ids', $user->id)
                ->orWhereJsonContains('reviewer_ids', (string)$user->id);
        })
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NULL THEN 1 ELSE 0 END) as pending')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue', [$now])
            ->first();

        // 3. إحصائيات الأداء لهذا الشهر (KPI)
        $monthStats = Task::where('assigned_to', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('COUNT(*) as total_this_month')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_this_month')
            ->first();

        $completionRate = $monthStats->total_this_month > 0
            ? round(($monthStats->completed_this_month / $monthStats->total_this_month) * 100)
            : 0;

        // 4. عدد الخطط
        $totalPlans = ContentPlan::whereHas('users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })->orWhereHas('posts', function ($q) use ($user) {
            $q->where('designer_id', $user->id)
                ->orWhereJsonContains('reviewer_ids', $user->id)
                ->orWhereJsonContains('reviewer_ids', (string)$user->id);
        })->count();

        // 5. الأولويات العاجلة: مهام
        $upcomingTasks = Task::with('project:id,name')
            ->where('assigned_to', $user->id)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->take(5)
            ->get();

        // 6. الأولويات العاجلة: منشورات
        $upcomingPosts = PlanPost::with(['plan:id,client_id', 'plan.client:id,name'])
            ->where(function($q) use ($user) {
                $q->where('designer_id', $user->id)
                    ->orWhereJsonContains('reviewer_ids', $user->id)
                    ->orWhereJsonContains('reviewer_ids', (string)$user->id);
            })
            ->whereNull('delivered_at')
            ->whereNotNull('deadline')
            ->orderBy('deadline', 'asc')
            ->take(5)
            ->get();

        // إرجاع البيانات مهيكلة لتناسب الفرونت إند الجديد
        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'tasks' => [
                        'total' => (int) $taskStats->total,
                        'completed' => (int) $taskStats->completed,
                        'pending' => (int) $taskStats->pending,
                        'overdue' => (int) $taskStats->overdue,
                    ],
                    'posts' => [
                        'total' => (int) $postStats->total,
                        'completed' => (int) $postStats->completed,
                        'pending' => (int) $postStats->pending,
                        'overdue' => (int) $postStats->overdue,
                    ],
                    'total_plans' => $totalPlans,
                ],
                'kpi' => [
                    'completion_rate' => $completionRate,
                    'total_this_month' => (int) $monthStats->total_this_month,
                ],
                'priorities' => [
                    'upcoming_tasks' => $upcomingTasks,
                    'upcoming_posts' => $upcomingPosts,
                ]
            ]
        ]);
    }
}

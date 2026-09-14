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

        // 2. إحصائيات منشورات الخطط (Posts Stats)
        $postStats = PlanPost::where(function($q) use ($user) {
            $q->where('designer_id', $user->id)
                ->orWhereJsonContains('reviewer_ids', $user->id)
                ->orWhereJsonContains('reviewer_ids', (string)$user->id);
        })
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NULL THEN 1 ELSE 0 END) as pending')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue', [$now])
            ->first();

        // 3. أداء الشهر الحالي للمهام (Tasks KPI)
        $monthTaskStats = Task::where('assigned_to', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->first();

        $taskCompletionRate = $monthTaskStats->total > 0
            ? round(($monthTaskStats->completed / $monthTaskStats->total) * 100)
            : 0;

        // 4. أداء الشهر الحالي للمنشورات (Posts KPI) - الجديد!
        $monthPostStats = PlanPost::where(function($q) use ($user) {
            $q->where('designer_id', $user->id)
                ->orWhereJsonContains('reviewer_ids', $user->id)
                ->orWhereJsonContains('reviewer_ids', (string)$user->id);
        })
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed')
            ->first();

        $postCompletionRate = $monthPostStats->total > 0
            ? round(($monthPostStats->completed / $monthPostStats->total) * 100)
            : 0;

        // 5. عدد الخطط المشارك بها
        $totalPlans = ContentPlan::whereHas('users', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })->orWhereHas('posts', function ($q) use ($user) {
            $q->where('designer_id', $user->id)
                ->orWhereJsonContains('reviewer_ids', $user->id)
                ->orWhereJsonContains('reviewer_ids', (string)$user->id);
        })->count();

        // 6. الأولويات العاجلة: مهام
        $upcomingTasks = Task::with('project:id,name')
            ->where('assigned_to', $user->id)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->take(5)
            ->get();

        // 7. الأولويات العاجلة: منشورات
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

        // إرجاع البيانات مهيكلة
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
                    'tasks' => [
                        'completion_rate' => $taskCompletionRate,
                        'total_this_month' => (int) $monthTaskStats->total,
                    ],
                    'posts' => [
                        'completion_rate' => $postCompletionRate,
                        'total_this_month' => (int) $monthPostStats->total,
                    ],
                ],
                'priorities' => [
                    'upcoming_tasks' => $upcomingTasks,
                    'upcoming_posts' => $upcomingPosts,
                ]
            ]
        ]);
    }
}

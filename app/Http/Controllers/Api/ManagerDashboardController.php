<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Project;
use App\Models\Task;
use App\Models\ContentPlan;
use App\Models\PlanPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ManagerDashboardController extends Controller
{
    /**
     * جلب الرؤى والتحليلات الشاملة لداشبورد المدير (بدون جدول الموظفين)
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك بالوصول'], 403);
        }

        $now = Carbon::now();

        // 1. إحصائيات المشاريع
        $projectStats = Project::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "جاري التنفيذ" THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = "مكتمل" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "متوقف" THEN 1 ELSE 0 END) as stopped
        ')->first();

        // 2. إحصائيات خطط المحتوى التفصيلية (🔴 تم التحديث بناءً على حالات قاعدة البيانات 🔴)
        $planStats = ContentPlan::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = "under_review" THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = "reviewed" THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
        ')->first();

        // 3. إحصائيات المهام الشاملة
        $taskStats = Task::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status != "completed" AND due_date < ? THEN 1 ELSE 0 END) as overdue
        ', [$now])->first();

        // 4. إحصائيات المنشورات
        $postStats = PlanPost::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN delivered_at IS NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN finance_status = "ممول" THEN 1 ELSE 0 END) as funded,
            SUM(CASE WHEN finance_status = "غير ممول" THEN 1 ELSE 0 END) as unfunded,
            SUM(CASE WHEN review_status = "مرفوض" OR manager_review_status = "مرفوض" THEN 1 ELSE 0 END) as rejected
        ', [$now])->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'overview' => [
                    'projects' => [
                        'total' => (int) $projectStats->total,
                        'active' => (int) $projectStats->active,
                        'completed' => (int) $projectStats->completed,
                        'stopped' => (int) $projectStats->stopped,
                    ],
                ],
                // 🔴 تم تحديث مفاتيح الـ JSON هنا 🔴
                'plan_stats' => [
                    'total' => (int) $planStats->total,
                    'pending' => (int) $planStats->pending,
                    'under_review' => (int) $planStats->under_review,
                    'reviewed' => (int) $planStats->reviewed,
                    'completed' => (int) $planStats->completed,
                    'rejected' => (int) $planStats->rejected,
                ],
                'global_stats' => [
                    'tasks' => [
                        'total' => (int) $taskStats->total,
                        'completed' => (int) $taskStats->completed,
                        'overdue' => (int) $taskStats->overdue,
                    ],
                    'posts' => [
                        'total' => (int) $postStats->total,
                        'completed' => (int) $postStats->completed,
                        'overdue' => (int) $postStats->overdue,
                        'funded' => (int) $postStats->funded,
                        'unfunded' => (int) $postStats->unfunded,
                        'rejected' => (int) $postStats->rejected,
                    ]
                ]
            ]
        ]);
    }

    /**
     * جلب جدول الموظفين مع الـ Pagination والتفاصيل العميقة (مهام، تنفيذ، مراجعة)
     */
    public function leaderboard(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $now = Carbon::now();

        // 1. استبعاد المديرين وجلب إحصائيات (المهام) بدقة
        $users = User::where('role', '!=', 'manager')
            ->withCount([
                'assignedTasks as total_tasks',
                'assignedTasks as completed_tasks' => fn($q) => $q->where('status', 'completed'),
                'assignedTasks as overdue_tasks' => fn($q) => $q->where('status', '!=', 'completed')->whereNotNull('due_date')->where('due_date', '<', $now),
                'assignedTasks as pending_tasks' => fn($q) => $q->where('status', '!=', 'completed')->where(function($sq) use ($now) {
                    $sq->whereNull('due_date')->orWhere('due_date', '>=', $now);
                }),
            ])->get()->map(function ($user) use ($now) {

                // 2. إحصائيات المنشورات كـ (منفذ / مصمم)
                $execStats = PlanPost::where('designer_id', $user->id)
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN delivered_at IS NULL AND (deadline IS NULL OR deadline >= ?) THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN delivered_at IS NULL AND deadline IS NOT NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue
                    ', [$now, $now])->first();

                // 3. إحصائيات المنشورات كـ (مراجع)
                $revStats = PlanPost::where(function($q) use ($user) {
                    $q->whereJsonContains('reviewer_ids', $user->id)
                        ->orWhereJsonContains('reviewer_ids', (string)$user->id);
                })
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN delivered_at IS NULL AND (deadline IS NULL OR deadline >= ?) THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN delivered_at IS NULL AND deadline IS NOT NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue
                    ', [$now, $now])->first();

                // 4. الحسابات الكلية (المعادلة الرياضية المتطابقة 100%)
                $overall_total = $user->total_tasks + (int)$execStats->total + (int)$revStats->total;
                $overall_completed = $user->completed_tasks + (int)$execStats->completed + (int)$revStats->completed;
                $overall_pending = $user->pending_tasks + (int)$execStats->pending + (int)$revStats->pending;
                $overall_overdue = $user->overdue_tasks + (int)$execStats->overdue + (int)$revStats->overdue;

                // التقييم وحالة الموظف بناءً على إجمالي المتأخرات مقابل إجمالي العمل المطلوب
                $overdueRate = $overall_total > 0 ? round(($overall_overdue / $overall_total) * 100) : 0;

                $health_status = 'safe';
                if ($overdueRate > 0 && $overdueRate <= 20) $health_status = 'warning';
                if ($overdueRate > 20) $health_status = 'danger';

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'health_status' => $health_status,
                    'overdue_rate' => $overdueRate,

                    // تقسيم الأرقام ليسهل على الفرونت إند عرضها بهيكلية الشجرة (Tree Structure)
                    'metrics' => [
                        'total' => [
                            'overall' => $overall_total,
                            'tasks' => $user->total_tasks,
                            'exec_posts' => (int)$execStats->total,
                            'rev_posts' => (int)$revStats->total,
                        ],
                        'pending' => [
                            'overall' => $overall_pending,
                            'tasks' => $user->pending_tasks,
                            'exec_posts' => (int)$execStats->pending,
                            'rev_posts' => (int)$revStats->pending,
                        ],
                        'completed' => [
                            'overall' => $overall_completed,
                            'tasks' => $user->completed_tasks,
                            'exec_posts' => (int)$execStats->completed,
                            'rev_posts' => (int)$revStats->completed,
                        ],
                        'overdue' => [
                            'overall' => $overall_overdue,
                            'tasks' => $user->overdue_tasks,
                            'exec_posts' => (int)$execStats->overdue,
                            'rev_posts' => (int)$revStats->overdue,
                        ],
                    ]
                ];
            });

        // 5. الترتيب والـ Pagination
        $sorted = $users->sortByDesc('overdue_rate')->values();

        $page = $request->get('page', 1);
        $perPage = 5; // يمكنك زيادتها لـ 10 أو 15 لو أردت
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'status' => 'success',
            'data' => $paginated
        ]);
    }
}

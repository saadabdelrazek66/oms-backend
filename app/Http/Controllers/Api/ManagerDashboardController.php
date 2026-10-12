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
use App\Modules\QuickTasks\Models\QuickTask;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ManagerDashboardController extends Controller
{
    /**
     * جلب الرؤى والتحليلات الشاملة لداشبورد المدير (بدون جدول الموظفين)
     */
    /**
     * تحديد نطاق التاريخ بناءً على Time Horizon المطلوب
     */
    private function parseTimeHorizon(Request $request): array
    {
        $range = $request->input('range', 'all');
        $startDate = null;
        $endDate = null;
        $label = 'الكل (تراكمي)';

        switch ($range) {
            case 'today':
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
                $label = 'اليوم (' . $startDate->format('Y-m-d') . ')';
                break;
            case 'this_week':
                // أسبوع العمل العربي يبدأ السبت وينتهي الجمعة
                $startDate = Carbon::now()->startOfWeek(Carbon::SATURDAY)->startOfDay();
                $endDate = Carbon::now()->endOfWeek(Carbon::FRIDAY)->endOfDay();
                $label = 'هذا الأسبوع (' . $startDate->format('m/d') . ' إلى ' . $endDate->format('m/d') . ')';
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth()->startOfDay();
                $endDate = Carbon::now()->endOfMonth()->endOfDay();
                $label = 'هذا الشهر (' . Carbon::now()->format('F Y') . ')';
                break;
            case 'all':
            default:
                $range = 'all';
                $startDate = null;
                $endDate = null;
                $label = 'الكل (تراكمي)';
                break;
        }

        return [
            'range' => $range,
            'label' => $label,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * جلب الرؤى والتحليلات الشاملة لداشبورد المدير (بدون جدول الموظفين)
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك بالوصول'], 403);
        }

        $now = Carbon::now();
        $horizon = $this->parseTimeHorizon($request);
        $startDate = $horizon['start_date'];
        $endDate = $horizon['end_date'];

        // 1. إحصائيات المشاريع
        $projectQuery = Project::query();
        if ($startDate && $endDate) {
            $projectQuery->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $endDate)
                          ->where(function($e) use ($startDate) {
                              $e->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
                          });
                  });
            });
        }
        $projectStats = $projectQuery->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "جاري التنفيذ" THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = "مكتمل" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "متوقف" THEN 1 ELSE 0 END) as stopped
        ')->first();

        // 2. إحصائيات خطط المحتوى التفصيلية
        $planQuery = ContentPlan::query();
        if ($startDate && $endDate) {
            $planQuery->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $endDate)
                          ->where('end_date', '>=', $startDate);
                  });
            });
        }
        $planStats = $planQuery->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = "under_review" THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = "reviewed" THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
        ')->first();

        // 3. إحصائيات المهام الشاملة
        $taskQuery = Task::query();
        if ($startDate && $endDate) {
            $taskQuery->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('due_date', [$startDate, $endDate])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->whereNull('due_date')->whereBetween('created_at', [$startDate, $endDate]);
                  });
            });
        }
        $taskStats = $taskQuery->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status != "completed" AND due_date < ? THEN 1 ELSE 0 END) as overdue
        ', [$now])->first();

        // 4. إحصائيات المنشورات
        $postQuery = PlanPost::query();
        if ($startDate && $endDate) {
            $postQuery->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('target_date', [$startDate, $endDate])
                  ->orWhere(function($sub) use ($startDate, $endDate) {
                      $sub->whereNull('target_date')->whereBetween('deadline', [$startDate, $endDate]);
                  });
            });
        }
        $postStats = $postQuery->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN delivered_at IS NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN finance_status = "ممول" THEN 1 ELSE 0 END) as funded,
            SUM(CASE WHEN finance_status = "غير ممول" THEN 1 ELSE 0 END) as unfunded,
            SUM(CASE WHEN review_status = "مرفوض" OR manager_review_status = "مرفوض" THEN 1 ELSE 0 END) as rejected
        ', [$now])->first();

        // 5. مركز قرارات واعتمادات المدير (Executive Action Hub)
        // أ. خطط المحتوى التي أصبحت under_review وبانتظار اعتماد المدير
        $plansAwaitingApprovalQuery = ContentPlan::with(['client:id,name,logo'])
            ->withCount('posts')
            ->where('status', 'under_review');
        $allPlansUnderReviewCount = (clone $plansAwaitingApprovalQuery)->count();

        // ب. المنشورات المرفوضة التي أعاد المصممون رفعها للمراجعة، أو معتمدة من القسم وبانتظار اعتماد المدير
        $postsAwaitingManagerQuery = PlanPost::with([
                'designer:id,name',
                'plan:id,name,client_id',
                'plan.client:id,name'
            ])
            ->where(function ($q) {
                $q->whereIn('manager_review_status', ['قيد الانتظار', 'معلق'])
                  ->where(function ($sub) {
                      $sub->where('review_status', 'معتمد')
                          ->orWhereNotNull('manager_rejection_history');
                  });
            });
        $allPostsAwaitingManagerCount = (clone $postsAwaitingManagerQuery)->count();

        // ج. المهام السريعة المسلمة (under_review / pending_review) مع الصوت والتفاصيل
        $quickTasksAwaitingReviewQuery = QuickTask::with([
                'assignee:id,name,phone',
                'feedbackLogs' => fn($q) => $q->latest()->limit(1)
            ])
            ->whereIn('status', ['under_review', 'pending_review']);
        $allQuickTasksPendingCount = (clone $quickTasksAwaitingReviewQuery)->count();

        $totalAllTimePending = $allPlansUnderReviewCount + $allPostsAwaitingManagerCount + $allQuickTasksPendingCount;

        // تطبيق الفلتر الزمني على Action Hub إذا لم يكن 'all'
        if ($startDate && $endDate) {
            $plansAwaitingApprovalQuery->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('updated_at', [$startDate, $endDate])
                  ->orWhereBetween('planned_review_date', [$startDate, $endDate]);
            });
            $postsAwaitingManagerQuery->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('updated_at', [$startDate, $endDate])
                  ->orWhereBetween('target_date', [$startDate, $endDate]);
            });
            $quickTasksAwaitingReviewQuery->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('updated_at', [$startDate, $endDate])
                  ->orWhereBetween('deadline', [$startDate, $endDate]);
            });
        }

        $totalPlansUnderReview = (clone $plansAwaitingApprovalQuery)->count();
        $plansAwaitingApproval = $plansAwaitingApprovalQuery
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name ?: "خطة #{$plan->id}",
                    'client_name' => $plan->client ? $plan->client->name : 'عميل غير محدد',
                    'client_logo' => $plan->client && $plan->client->logo ? asset($plan->client->logo) : null,
                    'posts_count' => (int) $plan->posts_count,
                    'plan_type' => $plan->plan_type ?? 'خطة محتوى',
                    'planned_review_date' => $plan->planned_review_date ? $plan->planned_review_date->format('Y-m-d') : null,
                    'time_ago' => $plan->updated_at ? $plan->updated_at->diffForHumans() : '',
                    'url' => "/content-plans?plan_id={$plan->id}",
                ];
            });

        $totalPostsAwaitingManager = (clone $postsAwaitingManagerQuery)->count();
        $postsAwaitingManager = $postsAwaitingManagerQuery
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(function ($post) {
                $isResubmitted = !empty($post->manager_rejection_history);
                return [
                    'id' => $post->id,
                    'content_plan_id' => $post->content_plan_id,
                    'plan_name' => $post->plan ? $post->plan->name : "خطة #{$post->content_plan_id}",
                    'client_name' => ($post->plan && $post->plan->client) ? $post->plan->client->name : 'عميل غير محدد',
                    'designer_name' => $post->designer ? $post->designer->name : 'غير محدد',
                    'target_date' => $post->target_date ? Carbon::parse($post->target_date)->format('Y-m-d') : null,
                    'is_resubmitted' => $isResubmitted,
                    'review_status' => $post->review_status,
                    'manager_review_status' => $post->manager_review_status,
                    'time_ago' => $post->updated_at ? $post->updated_at->diffForHumans() : '',
                    'url' => "/plan-board/{$post->content_plan_id}?post_id={$post->id}",
                ];
            });

        $totalQuickTasksPending = (clone $quickTasksAwaitingReviewQuery)->count();
        $quickTasksAwaitingReview = $quickTasksAwaitingReviewQuery
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(function ($qt) {
                $latestLog = $qt->feedbackLogs->first();
                $audioUrl = ($latestLog && $latestLog->voice_record_path) 
                    ? asset($latestLog->voice_record_path) 
                    : ($qt->voice_record_path ? asset($qt->voice_record_path) : null);
                $attachmentUrl = ($latestLog && $latestLog->attachment_path) 
                    ? asset($latestLog->attachment_path) 
                    : ($qt->attachment_path ? asset($qt->attachment_path) : null);

                return [
                    'id' => $qt->id,
                    'title' => $qt->title ?: "توجيه سريع #{$qt->id}",
                    'description' => $qt->description,
                    'feedback_notes' => $latestLog ? $latestLog->feedback_text : null,
                    'assignee_name' => $qt->assignee ? $qt->assignee->name : 'غير محدد',
                    'voice_record_url' => $audioUrl,
                    'attachment_url' => $attachmentUrl,
                    'deadline' => $qt->deadline ? $qt->deadline->format('Y-m-d H:i') : null,
                    'time_ago' => $qt->updated_at ? $qt->updated_at->diffForHumans() : '',
                    'url' => "/quick-tasks?task_id={$qt->id}",
                ];
            });

        $totalActionHubPending = $totalPlansUnderReview + $totalPostsAwaitingManager + $totalQuickTasksPending;

        return response()->json([
            'status' => 'success',
            'data' => [
                'time_horizon' => [
                    'range' => $horizon['range'],
                    'label' => $horizon['label'],
                    'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
                    'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
                ],
                'overview' => [
                    'projects' => [
                        'total' => (int) $projectStats->total,
                        'active' => (int) $projectStats->active,
                        'completed' => (int) $projectStats->completed,
                        'stopped' => (int) $projectStats->stopped,
                    ],
                ],
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
                ],
                // ⭐ مركز قرارات واعتمادات المدير (Executive Action Hub)
                'action_hub' => [
                    'total_pending' => $totalActionHubPending,
                    'total_all_time' => $totalAllTimePending,
                    'counts' => [
                        'plans' => $totalPlansUnderReview,
                        'posts' => $totalPostsAwaitingManager,
                        'quick_tasks' => $totalQuickTasksPending,
                    ],
                    'plans' => $plansAwaitingApproval,
                    'posts' => $postsAwaitingManager,
                    'quick_tasks' => $quickTasksAwaitingReview,
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
        $horizon = $this->parseTimeHorizon($request);
        $startDate = $horizon['start_date'];
        $endDate = $horizon['end_date'];

        // 1. استبعاد المديرين وجلب إحصائيات (المهام) بدقة
        $userQuery = User::where('role', '!=', 'manager');

        if ($startDate && $endDate) {
            $userQuery->withCount([
                'assignedTasks as total_tasks' => fn($q) => $q->where(fn($sq) => $sq->whereBetween('due_date', [$startDate, $endDate])->orWhere(fn($ssq) => $ssq->whereNull('due_date')->whereBetween('created_at', [$startDate, $endDate]))),
                'assignedTasks as completed_tasks' => fn($q) => $q->where('status', 'completed')->where(fn($sq) => $sq->whereBetween('due_date', [$startDate, $endDate])->orWhere(fn($ssq) => $ssq->whereNull('due_date')->whereBetween('created_at', [$startDate, $endDate]))),
                'assignedTasks as overdue_tasks' => fn($q) => $q->where('status', '!=', 'completed')->whereNotNull('due_date')->where('due_date', '<', $now)->where('due_date', '>=', $startDate),
                'assignedTasks as pending_tasks' => fn($q) => $q->where('status', '!=', 'completed')->where(function($sq) use ($now, $startDate, $endDate) {
                    $sq->where(fn($sub) => $sub->whereNull('due_date')->whereBetween('created_at', [$startDate, $endDate]))
                       ->orWhere(fn($sub) => $sub->where('due_date', '>=', $now)->where('due_date', '<=', $endDate));
                }),
            ]);
        } else {
            $userQuery->withCount([
                'assignedTasks as total_tasks',
                'assignedTasks as completed_tasks' => fn($q) => $q->where('status', 'completed'),
                'assignedTasks as overdue_tasks' => fn($q) => $q->where('status', '!=', 'completed')->whereNotNull('due_date')->where('due_date', '<', $now),
                'assignedTasks as pending_tasks' => fn($q) => $q->where('status', '!=', 'completed')->where(function($sq) use ($now) {
                    $sq->whereNull('due_date')->orWhere('due_date', '>=', $now);
                }),
            ]);
        }

        $users = $userQuery->get()->map(function ($user) use ($now, $startDate, $endDate) {

            // 2. إحصائيات المنشورات كـ (منفذ / مصمم)
            $execQuery = PlanPost::where('designer_id', $user->id);
            if ($startDate && $endDate) {
                $execQuery->where(fn($q) => $q->whereBetween('target_date', [$startDate, $endDate])->orWhere(fn($sq) => $sq->whereNull('target_date')->whereBetween('deadline', [$startDate, $endDate])));
            }
            $execStats = $execQuery->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN delivered_at IS NULL AND (deadline IS NULL OR deadline >= ?) THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN delivered_at IS NULL AND deadline IS NOT NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue
            ', [$now, $now])->first();

            // 3. إحصائيات المنشورات كـ (مراجع)
            $revQuery = PlanPost::where(function($q) use ($user) {
                $q->whereJsonContains('reviewer_ids', $user->id)
                    ->orWhereJsonContains('reviewer_ids', (string)$user->id);
            });
            if ($startDate && $endDate) {
                $revQuery->where(fn($q) => $q->whereBetween('target_date', [$startDate, $endDate])->orWhere(fn($sq) => $sq->whereNull('target_date')->whereBetween('deadline', [$startDate, $endDate])));
            }
            $revStats = $revQuery->selectRaw('
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
        $perPage = 5;
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'status' => 'success',
            'data' => $paginated,
            'time_horizon' => [
                'range' => $horizon['range'],
                'label' => $horizon['label'],
            ]
        ]);
    }

    /**
     * جلب العناصر التفصيلية للكروت الإحصائية الذكية في الدرج الجانبي (Offcanvas Drilldown Drawer)
     */
    public function drilldown(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك بالوصول'], 403);
        }

        $type = $request->input('type', 'overdue_tasks');
        $horizon = $this->parseTimeHorizon($request);
        $startDate = $horizon['start_date'];
        $endDate = $horizon['end_date'];
        $now = Carbon::now();

        $items = collect();
        $title = '';
        $icon = '';

        if ($type === 'overdue_tasks') {
            $title = 'المهام المتأخرة عن موعد التسليم';
            $icon = '🚨';
            $taskQuery = Task::with([
                'project:id,name',
                'assignee:id,name,phone,job_title',
                'creator:id,name'
            ])
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', $now);

            if ($startDate && $endDate) {
                $taskQuery->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('due_date', [$startDate, $endDate])
                      ->orWhere(function($sub) use ($startDate, $endDate) {
                          $sub->whereNull('due_date')->whereBetween('created_at', [$startDate, $endDate]);
                      });
                });
            }

            $tasks = $taskQuery->orderBy('due_date', 'asc')->get();

            $items = $tasks->map(function($task) use ($now) {
                $dueDate = $task->due_date ? Carbon::parse($task->due_date) : null;
                $daysOverdue = $dueDate ? (int) max(1, ceil($dueDate->diffInDays($now))) : 0;

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project?->name ?? 'مشروع عام',
                    'assignee_id' => $task->assignee?->id,
                    'assignee_name' => $task->assignee?->name ?? 'غير محدد',
                    'assignee_phone' => $task->assignee?->phone ?? null,
                    'assignee_job' => $task->assignee?->job_title ?? 'موظف',
                    'priority' => $task->priority ?? 'عادية',
                    'status' => $task->status,
                    'due_date' => $task->due_date ? Carbon::parse($task->due_date)->format('Y-m-d H:i') : null,
                    'due_date_raw' => $task->due_date ? Carbon::parse($task->due_date)->format('Y-m-d\TH:i') : null,
                    'days_overdue' => $daysOverdue,
                    'is_urgent' => (bool)$task->is_urgent,
                    'type' => 'task',
                ];
            });
        } elseif ($type === 'overdue_posts') {
            $title = 'المنشورات المتأخرة عن موعد التسليم';
            $icon = '⏳';
            $postQuery = PlanPost::with([
                'plan:id,plan_type,client_id',
                'plan.client:id,name',
                'designer:id,name,phone,job_title',
                'reviewer:id,name,phone'
            ])
            ->whereNull('delivered_at')
            ->where('deadline', '<', $now);

            if ($startDate && $endDate) {
                $postQuery->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('target_date', [$startDate, $endDate])
                      ->orWhere(function($sub) use ($startDate, $endDate) {
                          $sub->whereNull('target_date')->whereBetween('deadline', [$startDate, $endDate]);
                      });
                });
            }

            $posts = $postQuery->orderBy('deadline', 'asc')->get();

            $items = $posts->map(function($post) use ($now) {
                $deadline = $post->deadline ? Carbon::parse($post->deadline) : null;
                $daysOverdue = $deadline ? (int) max(1, ceil($deadline->diffInDays($now))) : 0;
                $displayTitle = $post->objective ?: ($post->detailed_idea ?: ($post->caption ?: "منشور رقم #{$post->id}"));

                return [
                    'id' => $post->id,
                    'title' => Str::limit($displayTitle, 90),
                    'plan_id' => $post->content_plan_id,
                    'plan_name' => $post->plan?->client?->name ?? ($post->plan?->plan_type ? "خطة {$post->plan->plan_type}" : "خطة #{$post->content_plan_id}"),
                    'post_type' => $post->post_type ?? 'منشور',
                    'designer_id' => $post->designer?->id,
                    'designer_name' => $post->designer?->name ?? 'غير محدد',
                    'designer_phone' => $post->designer?->phone ?? null,
                    'designer_job' => $post->designer?->job_title ?? 'مصمم',
                    'target_date' => $post->target_date ? Carbon::parse($post->target_date)->format('Y-m-d') : null,
                    'deadline' => $post->deadline ? Carbon::parse($post->deadline)->format('Y-m-d H:i') : null,
                    'days_overdue' => $daysOverdue,
                    'type' => 'post',
                ];
            });
        } elseif ($type === 'rejected_posts') {
            $title = 'المنشورات المرفوضة في المراجعة';
            $icon = '❌';
            $postQuery = PlanPost::with([
                'plan:id,plan_type,client_id',
                'plan.client:id,name',
                'designer:id,name,phone,job_title',
                'reviewer:id,name,phone'
            ])
            ->where(function($q) {
                $q->where('review_status', 'مرفوض')
                  ->orWhere('manager_review_status', 'مرفوض');
            });

            if ($startDate && $endDate) {
                $postQuery->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('target_date', [$startDate, $endDate])
                      ->orWhere(function($sub) use ($startDate, $endDate) {
                          $sub->whereNull('target_date')->whereBetween('deadline', [$startDate, $endDate]);
                      });
                });
            }

            $posts = $postQuery->orderBy('updated_at', 'desc')->get();

            $items = $posts->map(function($post) {
                $displayTitle = $post->objective ?: ($post->detailed_idea ?: ($post->caption ?: "منشور رقم #{$post->id}"));

                // استخراج سبب الرفض الأحدث
                $reason = null;
                if (!empty($post->manager_rejection_history) && is_array($post->manager_rejection_history)) {
                    $last = end($post->manager_rejection_history);
                    $reason = $last['reason'] ?? null;
                }
                if (!$reason && !empty($post->rejection_history) && is_array($post->rejection_history)) {
                    $last = end($post->rejection_history);
                    $reason = $last['reason'] ?? null;
                }

                return [
                    'id' => $post->id,
                    'title' => Str::limit($displayTitle, 90),
                    'plan_id' => $post->content_plan_id,
                    'plan_name' => $post->plan?->client?->name ?? ($post->plan?->plan_type ? "خطة {$post->plan->plan_type}" : "خطة #{$post->content_plan_id}"),
                    'post_type' => $post->post_type ?? 'منشور',
                    'designer_id' => $post->designer?->id,
                    'designer_name' => $post->designer?->name ?? 'غير محدد',
                    'designer_phone' => $post->designer?->phone ?? null,
                    'designer_job' => $post->designer?->job_title ?? 'مصمم',
                    'target_date' => $post->target_date ? Carbon::parse($post->target_date)->format('Y-m-d') : null,
                    'rejection_reason' => $reason ?: 'تعديلات مطلوبة بناءً على مراجعة الجودة',
                    'rejected_by' => $post->manager_review_status === 'مرفوض' ? 'المدير' : ($post->reviewer?->name ?? 'المراجع'),
                    'type' => 'rejected_post',
                ];
            });
        }

        return response()->json([
            'status' => 'success',
            'type' => $type,
            'title' => $title,
            'icon' => $icon,
            'count' => $items->count(),
            'data' => $items->values(),
            'time_horizon' => [
                'range' => $horizon['range'],
                'label' => $horizon['label'],
            ]
        ]);
    }
}

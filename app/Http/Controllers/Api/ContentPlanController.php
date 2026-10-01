<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentPlanRequest;
use App\Models\ContentPlan;
use App\Models\User; // تمت الإضافة لجلب المديرين
use App\Services\ContentPlanService;
use App\Rules\ValidDriveFileLink;
use App\Enums\DriveLinkType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification; // تمت الإضافة
use App\Modules\Notifications\Notifications\SystemNotification; // تمت الإضافة

class ContentPlanController extends Controller
{
    public function __construct(private ContentPlanService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ContentPlan::with(['users', 'client.driveLinks', 'reviewHistories.reviewer', 'clientFollowUps.user']);

        if ($user->role->value === 'employee') {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan_type')) {
            $query->where('plan_type', 'like', '%' . $request->plan_type . '%');
        }

        if ($user->role->value === 'manager' && $request->filled('employee_id')) {
            $query->whereHas('users', function ($q) use ($request) {
                $q->where('users.id', $request->employee_id);
            });
        }

        if ($request->has('requires_review')) {
            $query->where('requires_review', $request->boolean('requires_review'));
        }

        if ($request->boolean('is_overdue')) {
            $query->where('planned_delivery_date', '<', now()->format('Y-m-d'))
                ->where('status', '!=', 'completed'); 
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
            });
        }

        if ($request->filled('delivery_from') && $request->filled('delivery_to')) {
            $query->whereBetween('planned_delivery_date', [$request->delivery_from, $request->delivery_to]);
        }

        if ($request->filled('review_from') && $request->filled('review_to')) {
            $query->whereBetween('planned_review_date', [$request->review_from, $request->review_to]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('plan_type', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('client', function($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $plans = $query->orderBy('id', 'desc')->paginate(15);

        $plans->getCollection()->transform(function ($plan) {
            $reviewFolder = $plan->client->driveLinks->where('title', DriveLinkType::PLAN_REVIEW->value)->first();
            $finalFolder = $plan->client->driveLinks->where('title', DriveLinkType::PLAN_FINAL_DELIVERY->value)->first();

            $plan->folders = [
                'review_link' => $reviewFolder ? ($reviewFolder->url ?? $reviewFolder->link) : null, 
                'final_delivery_link' => $finalFolder ? ($finalFolder->url ?? $finalFolder->link) : null,
            ];

            return $plan;
        });

        return response()->json($plans);
    }

    public function boardPlans(Request $request)
    {
        $user = $request->user();

        $query = ContentPlan::with(['client']);

        $query->whereIn('status', ['reviewed', 'completed'])
              ->whereNotNull('actual_review_date');

        if ($user->role->value === 'employee') {
            $query->where(function ($q) use ($user) {
                $q->whereHas('users', function ($userQuery) use ($user) {
                    $userQuery->where('users.id', $user->id);
                })
                ->orWhereHas('posts', function ($postQuery) use ($user) {
                    $postQuery->where('designer_id', $user->id)
                              ->orWhereJsonContains('reviewer_ids', $user->id)
                              ->orWhereJsonContains('reviewer_ids', (string)$user->id);
                });
            });
        }

        $plans = $query->orderBy('id', 'desc')->paginate(8);

        return response()->json($plans);
    }

    // ==========================================
    // 1. إضافة خطة جديدة (إشعار للموظفين)
    // ==========================================
    public function store(ContentPlanRequest $request)
    {
        $plan = $this->service->createPlan($request->validated());
        $plan->load(['client', 'users']);

        Notification::send($plan->users, new SystemNotification([
            'title' => 'إسناد خطة عمل جديدة 🆕',
            'body' => "تم تعيينك للعمل على خطة المحتوى الخاصة بالعميل {$plan->client->name}.",
            'url' => "/plans/{$plan->id}",
            'icon' => 'document-add'
        ]));

        return response()->json([
            'message' => 'تم إنشاء الخطة بنجاح',
            'data' => $plan->load('reviewHistories.reviewer')
        ], 201);
    }

    // ==========================================
    // 2. تعديل خطة موجودة (إشعار للموظفين)
    // ==========================================
    public function update(ContentPlanRequest $request, ContentPlan $content_plan)
    {
        $plan = $this->service->updatePlan($content_plan, $request->validated());
        $plan->load(['client', 'users']);

        Notification::send($plan->users, new SystemNotification([
            'title' => 'تحديث في تفاصيل الخطة 🔄',
            'body' => "تم تعديل تفاصيل ومواعيد خطة العميل {$plan->client->name}.",
            'url' => "/plans/{$plan->id}",
            'icon' => 'refresh'
        ]));

        return response()->json([
            'message' => 'تم التعديل بنجاح',
            'data' => $plan->load('reviewHistories.reviewer')
        ]);
    }

    public function destroy(ContentPlan $content_plan)
    {
        $content_plan->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // ==========================================
    // 3. التسليم للمراجعة (إشعار للمديرين)
    // ==========================================
    public function submitForReview(Request $request, ContentPlan $content_plan)
    {
        $validated = $request->validate([
            'link' => ['required', 'url', new \App\Rules\ValidDriveFileLink()],
        ], [
            'link.required' => 'يجب إرفاق لينك الخطة لإتمام عملية التسليم للمراجعة.',
            'link.url' => 'الرابط المدخل غير صالح.'
        ]);

        $plan = $this->service->submitForReview($content_plan, $request->user()->id, $validated['link']);
        $plan->load('client');

        $managers = User::where('role', 'manager')->get();
        Notification::send($managers, new SystemNotification([
            'title' => 'خطة بانتظار المراجعة ⏳',
            'body' => "قام {$request->user()->name} بتسليم خطة العميل {$plan->client->name} للمراجعة الداخلية.",
            'url' => "/plans/{$plan->id}",
            'icon' => 'clock'
        ]));

        return response()->json([
            'message' => 'تم الإرسال للمراجعة الداخلية',
            'data' => $plan->load('reviewHistories.reviewer')
        ]);
    }

    // ==========================================
    // 4. التسليم النهائي (إشعار للمديرين)
    // ==========================================
    public function submitFinalDelivery(Request $request, ContentPlan $content_plan)
    {
        $validated = $request->validate([
            'link' => ['required', 'url', new \App\Rules\ValidDriveFileLink()],
        ], [
            'link.required' => 'مطلوب إرفاق لينك الخطة لإتمام تسليمها للعميل.',
            'link.url' => 'الرابط المدخل غير صالح.'
        ]);

        $plan = $this->service->submitFinalDelivery($content_plan, $validated['link']);
        $plan->load('client');

        $managers = User::where('role', 'manager')->get();
        Notification::send($managers, new SystemNotification([
            'title' => 'تسليم نهائي مكتمل 🚀',
            'body' => "تم التسليم النهائي لخطة العميل {$plan->client->name} للعميل بنجاح.",
            'url' => "/plans/{$plan->id}",
            'icon' => 'rocket'
        ]));

        return response()->json([
            'message' => 'تم التسليم النهائي بنجاح', 
            'data' => $plan->load('reviewHistories.reviewer')
        ]);
    }

    // ==========================================
    // 5. اعتماد الخطة (إشعار للموظفين)
    // ==========================================
    public function approvePlan(Request $request, ContentPlan $content_plan)
    {
        $plan = $this->service->approvePlan($content_plan, $request->user()->id);
        $plan->load(['client', 'users']);

        Notification::send($plan->users, new SystemNotification([
            'title' => 'تم اعتماد الخطة بنجاح ✅',
            'body' => "تم اعتماد خطة العميل {$plan->client->name} وهي الآن جاهزة للتسليم النهائي.",
            'url' => "/plans/{$plan->id}",
            'icon' => 'check-circle'
        ]));

        return response()->json([
            'message' => 'تم اعتماد الخطة وهي الآن جاهزة للتسليم', 
            'data' => $plan->load('reviewHistories.reviewer')
        ]);
    }

    // ==========================================
    // 6. رفض الخطة (إشعار للموظفين)
    // ==========================================
    public function rejectPlan(Request $request, ContentPlan $content_plan)
    {
        $request->validate(['notes' => 'required|string']);
        $plan = $this->service->rejectPlan($content_plan, $request->user()->id, $request->notes);
        $plan->load(['client', 'users']);

        Notification::send($plan->users, new SystemNotification([
            'title' => 'تعديلات مطلوبة على الخطة ⚠️',
            'body' => "قام المدير بإضافة ملاحظات على خطة العميل {$plan->client->name}، يرجى تعديلها.",
            'url' => "/plans/{$plan->id}",
            'icon' => 'exclamation-circle'
        ]));

        return response()->json([
            'message' => 'تم رفض الخطة وإرسال الملاحظات', 
            'data' => $plan->load('reviewHistories.reviewer')
        ]);
    }

    public function updateDetails(Request $request, ContentPlan $content_plan)
    {
        $validated = $request->validate([
            'final_link' => 'nullable|url',
            'notes' => 'nullable|string'
        ]);

        $content_plan->update($validated);
        return response()->json(['message' => 'تم تحديث التفاصيل', 'data' => $content_plan]);
    }

    // ==========================================
    // 7. استنساخ الخطة (إشعار للموظفين)
    // ==========================================
    public function duplicate(Request $request, ContentPlan $contentPlan)
    {
        $user = auth()->user();
        if ($user->role->value !== 'manager') {
            return response()->json(['message' => 'صلاحية الاستنساخ مخصصة للمدير فقط.'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'planned_delivery_date' => 'required|date|before_or_equal:start_date',
            'planned_review_date' => 'nullable|date|before_or_equal:planned_delivery_date',
            'planned_initial_delivery_date' => 'nullable|date|before_or_equal:planned_review_date', 
        ]);

        $newPlan = $contentPlan->replicate();

        $newPlan->start_date = $validated['start_date'];
        $newPlan->end_date = $validated['end_date'];
        $newPlan->planned_delivery_date = $validated['planned_delivery_date'];

        if (isset($validated['planned_review_date'])) {
            $newPlan->planned_review_date = $validated['planned_review_date'];
        }
        if (isset($validated['planned_initial_delivery_date'])) {
            $newPlan->planned_initial_delivery_date = $validated['planned_initial_delivery_date'];
        }

        $newPlan->actual_initial_delivery_date = null;
        $newPlan->actual_delivery_date = null;
        $newPlan->actual_review_date = null;
        $newPlan->final_link = null; 
        $newPlan->status = 'pending';

        $newPlan->save();

        $contentPlan->load('users');
        foreach ($contentPlan->users as $teamMember) {
            $newPlan->users()->attach($teamMember->id, [
                'task_role' => $teamMember->pivot->task_role
            ]);
        }

        $newPlan->load(['client', 'users']);

        Notification::send($newPlan->users, new SystemNotification([
            'title' => 'بدء خطة شهر جديد 📅',
            'body' => "تم استنساخ وتجديد خطة العميل {$newPlan->client->name} لشهر جديد وتم تعيينك بها.",
            'url' => "/plans/{$newPlan->id}",
            'icon' => 'calendar'
        ]));

        return response()->json([
            'message' => 'تم استنساخ الخطة بنجاح لبدء شهر جديد 🚀',
            'data' => $newPlan
        ], 201);
    }
    
    public function toggleRecurrence(Request $request, ContentPlan $contentPlan)
    {
        $user = auth()->user();

        if ($user->role->value !== 'manager') {
            return response()->json([
                'message' => 'صلاحية التحكم في التكرار التلقائي مخصصة للمدير فقط.'
            ], 403);
        }

        $contentPlan->is_recurring = !$contentPlan->is_recurring;
        $contentPlan->save();

        $statusAr = $contentPlan->is_recurring ? 'مُفعل 🟢' : 'مُتوقف 🔴';

        return response()->json([
            'message' => "تم تحديث إعدادات الخطة. التكرار التلقائي الآن: {$statusAr}",
            'data' => [
                'id' => $contentPlan->id,
                'is_recurring' => $contentPlan->is_recurring
            ]
        ], 200);
    }
}
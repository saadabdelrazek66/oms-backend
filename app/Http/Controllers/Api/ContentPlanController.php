<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentPlanRequest;
use App\Models\ContentPlan;
use App\Services\ContentPlanService;
use Illuminate\Http\Request;

class ContentPlanController extends Controller
{
    public function __construct(private ContentPlanService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ContentPlan::with(['users', 'client', 'reviewHistories.reviewer', 'clientFollowUps.user']);

        // 1. صلاحيات الموظف (يرى خططه فقط)
        if ($user->role->value === 'employee') {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        // ==========================================
        // 🔍 الفلاتر الإدارية (Manager Filters)
        // ==========================================

        // 2. فلتر بالعميل (لمعرفة كل خطط عميل معين)
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // 3. فلتر بحالة الخطة (إن وجدت لديك مثل: معلقة، قيد المراجعة، مكتملة)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 4. فلتر بنوع الخطة (مثال: SEO, Social Media)
        if ($request->filled('plan_type')) {
            $query->where('plan_type', 'like', '%' . $request->plan_type . '%');
        }

        // 5. فلتر بموظف محدد (للمدير: لمراقبة خطط موظف معين)
        if ($user->role->value === 'manager' && $request->filled('employee_id')) {
            $query->whereHas('users', function ($q) use ($request) {
                $q->where('users.id', $request->employee_id);
            });
        }

        // 6. فلتر الخطط التي تتطلب مراجعة فقط (أو التي لا تتطلب)
        if ($request->has('requires_review')) {
            $query->where('requires_review', $request->boolean('requires_review'));
        }

        // يجلب الخطط التي تجاوزت موعد التسليم ولم تكتمل بعد
        if ($request->boolean('is_overdue')) {
            $query->where('planned_delivery_date', '<', now()->format('Y-m-d'))
                ->where('status', '!=', 'completed'); // تأكد من اسم حالة الاكتمال لديك
        }

        // ==========================================
        // 📅 فلاتر التواريخ (Date Ranges)
        // ==========================================

        // 8. فلتر بفترة النشر (من - إلى)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
            });
        }

        // 9. فلتر بموعد التسليم النهائي (للبحث عن تسليمات هذا الأسبوع مثلاً)
        if ($request->filled('delivery_from') && $request->filled('delivery_to')) {
            $query->whereBetween('planned_delivery_date', [$request->delivery_from, $request->delivery_to]);
        }

        // 10. فلتر بموعد المراجعة
        if ($request->filled('review_from') && $request->filled('review_to')) {
            $query->whereBetween('planned_review_date', [$request->review_from, $request->review_to]);
        }

        // ==========================================
        // 🔎 فلتر البحث النصي الشامل (Global Search)
        // ==========================================
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

        return response()->json($query->orderBy('id', 'desc')->paginate(15));
    }

    public function boardPlans(Request $request)
    {
        $user = $request->user();

        // قمنا بجلب علاقة (العميل) فقط لأنها الوحيدة المستخدمة في واجهة الـ Boards
        $query = ContentPlan::with(['client']);

        if ($user->role->value === 'employee') {
            $query->where(function ($q) use ($user) {
                // 1. هل هو المسؤول عن الخطة بشكل عام؟
                $q->whereHas('users', function ($userQuery) use ($user) {
                    $userQuery->where('users.id', $user->id);
                })
                    // 2. أو هل له أي مهام داخل محتوى الخطة (منفذ أو مراجع)؟
                    ->orWhereHas('posts', function ($postQuery) use ($user) {
                        $postQuery->where(function ($subQuery) use ($user) {
                            $subQuery->where('designer_id', $user->id)
                                ->orWhereJsonContains('reviewer_ids', $user->id)
                                ->orWhereJsonContains('reviewer_ids', (string)$user->id);
                        });
                    });
            });
        }

        // يمكنك استخدام get() بدلاً من paginate() إذا كنت تريد عرض كل الكروت في الشاشة بدون صفحات
        return response()->json($query->orderBy('id', 'desc')->get());
    }

    // إضافة خطة جديدة
    public function store(ContentPlanRequest $request)
    {
        $plan = $this->service->createPlan($request->validated());
        return response()->json([
            'message' => 'تم إنشاء الخطة بنجاح',
            'data' => $plan->load('client', 'reviewHistories.reviewer')
        ], 201);
    }

    // تعديل خطة موجودة
    public function update(ContentPlanRequest $request, ContentPlan $content_plan)
    {
        $plan = $this->service->updatePlan($content_plan, $request->validated());
        return response()->json([
            'message' => 'تم التعديل بنجاح',
            'data' => $plan->load('client', 'reviewHistories.reviewer')
        ]);
    }

    public function destroy(ContentPlan $content_plan)
    {
        $content_plan->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // --- مسارات الإجراءات (Actions) ---

    public function submitForReview(ContentPlan $content_plan)
    {
        $plan = $this->service->submitForReview($content_plan);
        return response()->json(['message' => 'تم الإرسال للمراجعة الداخلية', 'data' => $plan->load('reviewHistories.reviewer')]);
    }

    public function submitFinalDelivery(ContentPlan $content_plan)
    {
        $plan = $this->service->submitFinalDelivery($content_plan);
        return response()->json(['message' => 'تم التسليم النهائي بنجاح', 'data' => $plan->load('reviewHistories.reviewer')]);
    }

    public function approvePlan(Request $request, ContentPlan $content_plan)
    {
        $plan = $this->service->approvePlan($content_plan, $request->user()->id);
        return response()->json(['message' => 'تم اعتماد الخطة وهي الآن جاهزة للتسليم', 'data' => $plan->load('reviewHistories.reviewer')]);
    }

    public function rejectPlan(Request $request, ContentPlan $content_plan)
    {
        $request->validate(['notes' => 'required|string']);
        $plan = $this->service->rejectPlan($content_plan, $request->user()->id, $request->notes);
        return response()->json(['message' => 'تم رفض الخطة وإرسال الملاحظات', 'data' => $plan->load('reviewHistories.reviewer')]);
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
    // ---- دالة استنساخ الخطة كقالب للشهر الجديد
    // ==========================================
    public function duplicate(Request $request, ContentPlan $contentPlan)
    {
        $user = auth()->user();
        if ($user->role->value !== 'manager') {
            return response()->json(['message' => 'صلاحية الاستنساخ مخصصة للمدير فقط.'], 403);
        }

        // 1. التحقق من التواريخ الجديدة القادمة من الواجهة
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'planned_delivery_date' => 'required|date|before_or_equal:start_date',
            'planned_review_date' => 'nullable|date|before_or_equal:planned_delivery_date',
        ], [
            'planned_delivery_date.before_or_equal' => 'موعد التسليم النهائي يجب أن يكون قبل أو مع تاريخ بداية الخطة.',
            'planned_review_date.before_or_equal' => 'موعد إنهاء المراجعة يجب أن يكون قبل أو مع موعد التسليم النهائي.',
            'end_date.after_or_equal' => 'تاريخ نهاية الخطة يجب أن يكون بعد أو مع تاريخ البداية.',
        ]);

        // 2. استنساخ الخطة (replicate تنسخ البيانات الأساسية فقط بدون الـ ID والعلاقات)
        $newPlan = $contentPlan->replicate();

        // 3. حقن التواريخ الجديدة وتصفير التواريخ الفعلية
        $newPlan->start_date = $validated['start_date'];
        $newPlan->end_date = $validated['end_date'];
        $newPlan->planned_delivery_date = $validated['planned_delivery_date'];

        if (isset($validated['planned_review_date'])) {
            $newPlan->planned_review_date = $validated['planned_review_date'];
        }

        // تصفير بيانات التنفيذ وإعادة الحالة للوضع الافتراضي (استخدام pending بدلاً من قيد التخطيط)
        $newPlan->actual_delivery_date = null;
        $newPlan->actual_review_date = null;
        $newPlan->status = 'pending';

        // 4. الحفظ
        $newPlan->save();

        // 5. استنساخ فريق العمل المربوط بالخطة القديمة (بالطريقة الآمنة لتجنب دمج الأدوار)
        $contentPlan->load('users'); // التأكد من تحميل المستخدمين
        foreach ($contentPlan->users as $teamMember) {
            // استخدام attach بداخل الـ loop لضمان إضافة الشخص حتى لو كان له أكثر من دور
            $newPlan->users()->attach($teamMember->id, [
                'task_role' => $teamMember->pivot->task_role
            ]);
        }

        // تحميل العلاقات لإرسالها للواجهة
        $newPlan->load(['client', 'users']);

        return response()->json([
            'message' => 'تم استنساخ الخطة بنجاح لبدء شهر جديد 🚀',
            'data' => $newPlan
        ], 201);
    }
}

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
        if ($user->role->value === 'employee') {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        return response()->json($query->orderBy('id', 'desc')->paginate(15));
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

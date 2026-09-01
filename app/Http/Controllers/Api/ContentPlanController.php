<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function store(Request $request)
    {
        $validated = $this->validatePlan($request);
        $plan = $this->service->createPlan($validated);
        return response()->json(['message' => 'تم إنشاء الخطة بنجاح', 'data' => $plan->load('client', 'reviewHistories.reviewer')], 201);
    }

    public function update(Request $request, ContentPlan $content_plan)
    {
        $validated = $this->validatePlan($request);
        $plan = $this->service->updatePlan($content_plan, $validated);
        return response()->json(['message' => 'تم التعديل بنجاح', 'data' => $plan->load('client', 'reviewHistories.reviewer')]);
    }

    public function destroy(ContentPlan $content_plan)
    {
        $content_plan->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // دالة مساعدة للتحقق من البيانات (بما فيها الشروط الإجبارية للمراجعة)
    private function validatePlan(Request $request)
    {
        // تحويل القيمة إلى Boolean
        $request->merge(['requires_review' => filter_var($request->requires_review, FILTER_VALIDATE_BOOLEAN)]);

        return $request->validate([
            'client_id' => 'required|exists:clients,id',
            'plan_type' => 'required|string|max:255',
            'requires_review' => 'boolean',
            'planned_delivery_date' => 'required|date',
            // تاريخ المراجعة إجباري فقط لو الخيار مفعل
            'planned_review_date' => 'required_if:requires_review,true|nullable|date',
            'responsible_ids' => 'nullable|array',
            // المراجع إجباري فقط لو الخيار مفعل
            'reviewer_ids' => 'required_if:requires_review,true|array',
            'executor_ids' => 'nullable|array',
            'final_link' => 'nullable|url',
            'notes' => 'nullable|string'
        ]);
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
}

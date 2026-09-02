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
}

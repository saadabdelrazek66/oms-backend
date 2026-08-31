<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPlan;
use App\Services\ContentPlanService;
use Illuminate\Http\Request;

class ContentPlanController extends Controller
{
    public function __construct(private ContentPlanService $service) {}

    // عرض الخطط (المدير يرى الكل - الموظف يرى خططه فقط)
    public function index(Request $request)
    {
        $user = $request->user();

        // التعديل هنا: جلب بيانات العميل مع كل خطة
        $query = ContentPlan::with(['users', 'client']);

        if ($user->role->value === 'employee') {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        return response()->json($query->orderBy('id', 'desc')->paginate(15));
    }

    // إضافة خطة (للمدير)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id', // التعديل هنا
            'plan_type' => 'required|string|max:255',
            'planned_delivery_date' => 'required|date',
            'planned_review_date' => 'required|date',
            'responsible_ids' => 'nullable|array',
            'specialist_ids' => 'nullable|array',
            'executor_ids' => 'nullable|array',
        ]);

        $plan = $this->service->createPlan($validated);
        $plan->load('client'); // جلب العميل بعد الإنشاء لإعادته في الاستجابة

        return response()->json(['message' => 'تم إنشاء الخطة بنجاح', 'data' => $plan], 201);
    }

    // تعديل خطة (للمدير)
    public function update(Request $request, ContentPlan $content_plan)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id', // التعديل هنا
            'plan_type' => 'required|string|max:255',
            'planned_delivery_date' => 'required|date',
            'planned_review_date' => 'required|date',
            'responsible_ids' => 'nullable|array',
            'specialist_ids' => 'nullable|array',
            'executor_ids' => 'nullable|array',
        ]);

        $plan = $this->service->updatePlan($content_plan, $validated);
        $plan->load('client'); // جلب العميل بعد التعديل

        return response()->json(['message' => 'تم التعديل بنجاح', 'data' => $plan]);
    }

    // حذف خطة (للمدير)
    public function destroy(ContentPlan $content_plan)
    {
        $content_plan->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // --- مسارات أفعال الموظف ---

    public function markReviewComplete(ContentPlan $content_plan)
    {
        $plan = $this->service->markReviewComplete($content_plan);
        return response()->json(['message' => 'تم تسجيل وقت المراجعة بنجاح', 'data' => $plan]);
    }

    public function markFinalDelivery(ContentPlan $content_plan)
    {
        $plan = $this->service->markFinalDelivery($content_plan);
        return response()->json(['message' => 'تم تسجيل وقت التسليم بنجاح', 'data' => $plan]);
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

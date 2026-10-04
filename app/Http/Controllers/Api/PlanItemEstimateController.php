<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanItemCategory;
use App\Models\PlanItemEstimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanItemEstimateController extends Controller
{
    public function index()
    {
        $categories = PlanItemCategory::with('items')->orderBy('sort_order')->orderBy('id')->get();
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $category = PlanItemCategory::create($validated);
        $category->load('items');

        return response()->json([
            'status' => 'success',
            'message' => 'تمت إضافة نوع الخطة بنجاح',
            'data' => $category
        ], 201);
    }

    public function updateCategory(Request $request, PlanItemCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $category->update($validated);
        $category->load('items');

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث نوع الخطة بنجاح',
            'data' => $category
        ]);
    }

    public function destroyCategory(PlanItemCategory $category)
    {
        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف نوع الخطة بنجاح'
        ]);
    }

    public function storeItem(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:plan_item_categories,id',
            'name' => 'required|string|max:255',
            'estimated_hours' => 'required|numeric|min:0',
            'unit' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $item = PlanItemEstimate::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'تمت إضافة العنصر بنجاح',
            'data' => $item
        ], 201);
    }

    public function updateItem(Request $request, PlanItemEstimate $estimate)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'estimated_hours' => 'sometimes|required|numeric|min:0',
            'category_id' => 'sometimes|required|exists:plan_item_categories,id',
            'sort_order' => 'nullable|integer',
        ]);

        $estimate->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث العنصر بنجاح',
            'data' => $estimate
        ]);
    }

    public function destroyItem(PlanItemEstimate $estimate)
    {
        $estimate->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف العنصر بنجاح'
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'estimates' => 'required|array',
            'estimates.*.id' => 'required|exists:plan_item_estimates,id',
            'estimates.*.estimated_hours' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['estimates'] as $row) {
                PlanItemEstimate::where('id', $row['id'])->update([
                    'estimated_hours' => $row['estimated_hours']
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'تم حفظ وتحديث كافة الساعات بنجاح ✅'
        ]);
    }
    /**
     * Lightweight endpoint to retrieve only ID and name of plan categories.
     * Ideal for ultra-fast dropdown selects in plan forms and filters.
     */
    public function options()
    {
        $categories = PlanItemCategory::with(['items:id,category_id,name,estimated_hours,unit'])->select('id', 'name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

}

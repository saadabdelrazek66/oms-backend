<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientFollowUp;
use App\Models\ContentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\FollowUpRequest;

class ClientFollowUpController extends Controller
{
    // دالة الإضافة
    public function store(FollowUpRequest $request, ContentPlan $content_plan)
    {
        $validated = $request->validated();
        $validated['user_id'] = auth()->id(); // ربط المتابعة بالموظف الحالي

        // إذا كان هناك صورة مرفقة، قم برفعها داخل مجلد 'follow_ups' في الـ public disk
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('follow_ups', 'public');
        }

        // ملاحظة: تأكد أن العلاقة في موديل ContentPlan اسمها clientFollowUps أو followUps حسب ما برمجته
        $followUp = $content_plan->clientFollowUps()->create($validated);

        return response()->json(['message' => 'تمت إضافة التحديث بنجاح', 'data' => $followUp], 201);
    }

    // دالة التعديل
    public function update(FollowUpRequest $request, $id)
    {
        $followUp = \App\Models\ClientFollowUp::findOrFail($id);
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            // مسح الصورة القديمة من السيرفر إذا كانت موجودة
            if ($followUp->image_path) {
                Storage::disk('public')->delete($followUp->image_path);
            }
            // رفع الصورة الجديدة
            $validated['image_path'] = $request->file('image')->store('follow_ups', 'public');
        }

        $followUp->update($validated);

        return response()->json(['message' => 'تم تعديل التحديث بنجاح', 'data' => $followUp]);
    }

    // دالة الحذف
    public function destroy($id)
    {
        $followUp = \App\Models\ClientFollowUp::findOrFail($id);

        // مسح الصورة من السيرفر قبل حذف السجل من قاعدة البيانات
        if ($followUp->image_path) {
            Storage::disk('public')->delete($followUp->image_path);
        }

        $followUp->delete();

        return response()->json(['message' => 'تم حذف التحديث بنجاح']);
    }
}

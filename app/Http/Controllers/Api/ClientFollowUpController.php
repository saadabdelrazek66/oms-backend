<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientFollowUp;
use App\Models\ContentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Http\Requests\FollowUpRequest;

class ClientFollowUpController extends Controller
{
    // دالة الإضافة
    public function store(FollowUpRequest $request, ContentPlan $content_plan)
    {
        $validated = $request->validated();
        $validated['user_id'] = auth()->id();

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            
            // نقل الملف فعلياً إلى مجلد public
            $image->move(public_path('uploads/follow_ups'), $imageName);
            
            // حفظ المسار في الداتابيز ليكون سهل الاستدعاء
            $validated['image_path'] = 'uploads/follow_ups/' . $imageName;
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
            // مسح الصورة القديمة مباشرة من مجلد public إذا كانت موجودة
            if ($followUp->image_path && File::exists(public_path($followUp->image_path))) {
                File::delete(public_path($followUp->image_path));
            }
            
            // رفع الصورة الجديدة
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/follow_ups'), $imageName);
            
            $validated['image_path'] = 'uploads/follow_ups/' . $imageName;
        }

        $followUp->update($validated);

        return response()->json(['message' => 'تم تعديل التحديث بنجاح', 'data' => $followUp]);
    }

    // دالة الحذف
    public function destroy($id)
    {
        $followUp = \App\Models\ClientFollowUp::findOrFail($id);

        // مسح الصورة من مجلد public قبل حذف السجل من قاعدة البيانات
        if ($followUp->image_path && File::exists(public_path($followUp->image_path))) {
            File::delete(public_path($followUp->image_path));
        }

        $followUp->delete();

        return response()->json(['message' => 'تم حذف التحديث بنجاح']);
    }
}
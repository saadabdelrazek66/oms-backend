<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientFollowUp;
use App\Models\ContentPlan;
use Illuminate\Http\Request;

class ClientFollowUpController extends Controller
{
    public function store(Request $request, ContentPlan $content_plan)
    {
        $request->validate(['content' => 'required|string']);

        $followUp = $content_plan->clientFollowUps()->create([
            'user_id' => $request->user()->id,
            // استخدمنا input() لتجنب التعارض مع الكلمة المحجوزة
            'content' => $request->input('content'),
        ]);

        return response()->json([
            'message' => 'تمت إضافة المتابعة بنجاح',
            'data' => $followUp->load('user')
        ], 201);
    }

    public function update(Request $request, ClientFollowUp $clientFollowUp)
    {
        if ($request->user()->role->value !== 'manager' && $request->user()->id !== $clientFollowUp->user_id) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذه المتابعة'], 403);
        }

        $request->validate(['content' => 'required|string']);

        // استخدمنا input() لتجنب التعارض مع الكلمة المحجوزة
        $clientFollowUp->content = $request->input('content');
        $clientFollowUp->save();

        return response()->json([
            'message' => 'تم التعديل بنجاح',
            'data' => $clientFollowUp->load('user')
        ]);
    }

    public function destroy(Request $request, ClientFollowUp $clientFollowUp)
    {
        if ($request->user()->role->value !== 'manager' && $request->user()->id !== $clientFollowUp->user_id) {
            return response()->json(['message' => 'غير مصرح لك بالحذف'], 403);
        }

        $clientFollowUp->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPlan;
use App\Models\PlanPost;
use Illuminate\Http\Request;

class PlanPostController extends Controller
{
    // 1. جلب جميع محتويات الخطة
    public function index(ContentPlan $plan)
    {
        // نجلب المنشورات مع أسماء المصمم والمراجع لعرضهم في الجدول
        $posts = $plan->posts()->with(['designer:id,name', 'reviewer:id,name'])->get();
        return response()->json(['data' => $posts]);
    }

    // 2. إضافة صف جديد لليوم الواحد (في حالة أراد العميل نشر أكثر من بوست في نفس اليوم)
    public function store(Request $request, ContentPlan $plan)
    {
        $request->validate([
            'target_date' => 'required|date'
        ]);

        $post = $plan->posts()->create([
            'target_date' => $request->target_date,
            'actual_publish_status' => 'لم يتم',
            'finance_status' => 'غير ممول',
        ]);

        // نرجع الصف الجديد بالكامل للفرونت إند ليتم رسمه في الجدول فوراً
        return response()->json([
            'message' => 'تم إضافة صف جديد',
            'data' => $post->load(['designer:id,name', 'reviewer:id,name'])
        ]);
    }

    // 1. التحديث الفوري المباشر (Inline Update) مع حماية حالة النشر
    public function update(Request $request, PlanPost $post)
    {
        // اللوجيك الأمني: منع النشر إذا لم تكتمل الموافقتين
        if ($request->has('actual_publish_status') && $request->actual_publish_status === 'تم النشر') {
            if ($post->review_status !== 'معتمد' || $post->manager_review_status !== 'معتمد') {
                return response()->json([
                    'message' => 'لا يمكن نشر المنشور قبل الحصول على موافقة المراجع واعتماد المدير النهائي.'
                ], 403); // 403 يعني ممنوع
            }
        }

        // إذا كانت الأمور سليمة، احفظ التعديل
        $post->update($request->all());

        return response()->json(['message' => 'تم الحفظ', 'data' => $post]);
    }

    // 2. دالة المراجعة المزدوجة (للمراجع والمدير)
    public function review(Request $request, PlanPost $post)
    {
        $request->validate([
            'review_type' => 'required|in:reviewer,manager',
            'status' => 'required|in:معتمد,مرفوض',
            'reason' => 'required_if:status,مرفوض|nullable|string|max:1000'
        ]);

        $user = auth()->user();


        $isManager = $user->role->value === 'manager';

        // 2. تحديد هل المستخدم هو المراجع المحدد؟ (استخدمنا == بدل === لتفادي مشاكل String/Int)
        $isReviewer = $user->id == $post->reviewer_id;

        // ---- مسار مراجعة المدير ----
        if ($request->review_type == 'manager') {
            if (!$isManager) {
                return response()->json(['message' => 'غير مصرح لك. هذه المراجعة خاصة بالمدير فقط.'], 403);
            }

            if ($request->status == 'مرفوض') {
                $history = $post->manager_rejection_history ?? [];
                $history[] = [
                    'reason' => $request->reason,
                    'date' => now()->format('Y-m-d H:i:s'),
                    'reviewer_name' => $user->name ?? 'المدير'
                ];
                $post->update([
                    'manager_review_status' => 'مرفوض',
                    'manager_rejection_history' => $history
                ]);
            } else {
                $post->update(['manager_review_status' => 'معتمد']);
            }
        }

        // ---- مسار مراجعة القسم (المراجع) ----
        else {
            // مسموح للمراجع الأصلي، ومسموح للمدير أيضاً
            if (!$isReviewer && !$isManager) {
                return response()->json(['message' => 'غير مصرح لك. هذه المراجعة خاصة بالمراجع المحدد أو المدير.'], 403);
            }

            if ($request->status == 'مرفوض') {
                $history = $post->rejection_history ?? [];
                $history[] = [
                    'reason' => $request->reason,
                    'date' => now()->format('Y-m-d H:i:s'),
                    'reviewer_name' => $user->name ?? 'المراجع'
                ];
                $post->update([
                    'review_status' => 'مرفوض',
                    'rejection_history' => $history
                ]);
            } else {
                $post->update(['review_status' => 'معتمد']);
            }
        }

        return response()->json([
            'message' => 'تم تسجيل المراجعة بنجاح.',
            'data' => $post
        ]);
    }
}

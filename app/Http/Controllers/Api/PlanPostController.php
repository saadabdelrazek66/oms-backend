<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPlan;
use App\Models\PlanPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanPostController extends Controller
{
    // 1. جلب جميع محتويات الخطة
    public function index(ContentPlan $plan)
    {
        $posts = $plan->posts()->get();

        // التحقق مما إذا كان المستخدم الحالي هو "مسئول" لهذه الخطة
        $isResponsible = DB::table('content_plan_user')
            ->where('content_plan_id', $plan->id)
            ->where('user_id', auth()->id())
            ->where('task_role', 'responsible')
            ->exists();

        return response()->json([
            'data' => $posts,
            'is_responsible' => $isResponsible // إرسال الحالة للفرونت إند
        ]);
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

    // 1. التحديث الفوري (Inline Update) - مع منع النشر قبل إجماع المراجعين واعتماد المدير
    public function update(Request $request, PlanPost $post)
    {
        if ($request->has('actual_publish_status') && $request->actual_publish_status === 'تم النشر') {
            if ($post->review_status !== 'معتمد' || $post->manager_review_status !== 'معتمد') {
                return response()->json([
                    'message' => 'لا يمكن نشر المنشور قبل الحصول على موافقة جميع المراجعين واعتماد المدير النهائي.'
                ], 403);
            }
        }

        $post->update($request->all());

        return response()->json(['message' => 'تم الحفظ', 'data' => $post]);
    }

    // 2. دالة المراجعة المزدوجة (إجماع المراجعين + المدير)
    public function review(Request $request, PlanPost $post)
    {
        $request->validate([
            'review_type' => 'required|in:reviewer,manager',
            'status' => 'required|in:معتمد,مرفوض',
            'reason' => 'required_if:status,مرفوض|nullable|string|max:1000'
        ]);

        $user = auth()->user();
        $userId = $user->id;

        $isManager = $user->role->value === 'manager';

        // ==========================================
        // ---- مسار مراجعة المدير (الاعتماد النهائي)
        // ==========================================
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

        // ==========================================
        // ---- مسار مراجعة القسم (تعدد المراجعين)
        // ==========================================
        else {
            $reviewerIds = $post->reviewer_ids ?? [];

            // التحقق: هل المستخدم الحالي من ضمن المراجعين المحددين؟
            $isReviewer = in_array($userId, $reviewerIds);

            // مسموح للمراجعين، ومسموح للمدير بالتدخل (كصلاحية إشرافية)
            if (!$isReviewer && !$isManager) {
                return response()->json(['message' => 'غير مصرح لك. لست ضمن قائمة المراجعين لهذا المنشور.'], 403);
            }

            // جلب سجل حالات المراجعين الحالي (مصفوفة تربط كل ID بقراره)
            $statuses = $post->reviewers_statuses ?? [];

            // --- حالة الرفض ---
            if ($request->status == 'مرفوض') {
                $statuses[$userId] = 'مرفوض'; // تسجيل رفض هذا الشخص

                $history = $post->rejection_history ?? [];
                $history[] = [
                    'reason' => $request->reason,
                    'date' => now()->format('Y-m-d H:i:s'),
                    'reviewer_name' => $user->name ?? 'المراجع'
                ];

                $post->update([
                    'reviewers_statuses' => $statuses,
                    'review_status' => 'مرفوض', // رفض المنشور فوراً للقسم
                    'rejection_history' => $history
                ]);
            }
            // --- حالة الموافقة ---
            else {
                $statuses[$userId] = 'معتمد'; // تسجيل موافقة هذا الشخص

                // إذا كان المتدخل هو المدير، يمكنه الموافقة عن الجميع بضغطة واحدة
                if ($isManager && !$isReviewer) {
                    $post->update([
                        'reviewers_statuses' => $statuses,
                        'review_status' => 'معتمد'
                    ]);
                } else {
                    // خوارزمية الإجماع: فحص ما إذا كان جميع المراجعين وافقوا
                    $allApproved = true;
                    if (count($reviewerIds) === 0) {
                        $allApproved = false;
                    } else {
                        foreach ($reviewerIds as $rId) {
                            if (!isset($statuses[$rId]) || $statuses[$rId] !== 'معتمد') {
                                $allApproved = false; // وجدنا شخصاً لم يوافق بعد
                                break;
                            }
                        }
                    }

                    $post->update([
                        'reviewers_statuses' => $statuses,
                        'review_status' => $allApproved ? 'معتمد' : 'قيد الانتظار'
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'تم تسجيل المراجعة بنجاح.',
            'data' => $post
        ]);
    }

}

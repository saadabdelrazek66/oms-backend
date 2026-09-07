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

    // 1. التحديث الفوري (Inline Update) مع قفل المراجعة الذكي وتصفير الأوقات
    public function update(Request $request, PlanPost $post)
    {
        $user = auth()->user();
        $isManager = $user->role->value === 'manager';
        $isExecutor = $user->id == $post->designer_id;

        // --- 1. قفل المراجعة الذكي (Review Lock-down) ---
        if (!$isManager && $isExecutor) {
            $isDelivered = !is_null($post->delivered_at);
            $isRejected = ($post->review_status === 'مرفوض' || $post->manager_review_status === 'مرفوض');
            $isFullyApproved = ($post->review_status === 'معتمد' && $post->manager_review_status === 'معتمد');

            // إذا تم التسليم، ولم يُرفض، ولم يتم اعتماده كلياً (أي أنه يقرأه المراجعون الآن)
            if ($isDelivered && !$isRejected && !$isFullyApproved) {
                return response()->json([
                    'message' => 'عفواً، المنشور قيد المراجعة حالياً. تم قفل المنشور ولا يمكنك التعديل عليه إلا في حال تم رفضه.'
                ], 403);
            }
        }

        // --- 2. حماية حقول التسليم (روابط التسليم ووقت التسليم) ---
        if ($request->has('delivery_links') || $request->has('delivered_at')) {
            if (!$isManager && !$isExecutor) {
                return response()->json([
                    'message' => 'غير مصرح لك. فقط المنفذ المكلف أو المدير يمكنهم إضافة روابط التسليم.'
                ], 403);
            }
        }

        // --- 3. حماية النشر (منع النشر قبل إجماع المراجعين واعتماد المدير) ---
        if ($request->has('actual_publish_status') && $request->actual_publish_status === 'تم النشر') {
            if ($post->review_status !== 'معتمد' || $post->manager_review_status !== 'معتمد') {
                return response()->json([
                    'message' => 'لا يمكن نشر المنشور قبل الحصول على موافقة جميع المراجعين واعتماد المدير النهائي.'
                ], 403);
            }
        }

        // --- 4. تجهيز البيانات وتصفير الأوقات في حالة التراجع اليدوي ---
        $data = $request->all();
        if (isset($data['review_status']) && $data['review_status'] === 'قيد الانتظار') {
            $data['department_approved_at'] = null; // تصفير وقت القسم
        }
        if (isset($data['manager_review_status']) && $data['manager_review_status'] === 'قيد الانتظار') {
            $data['manager_approved_at'] = null; // تصفير وقت المدير
        }

        // حفظ التعديلات
        $post->update($data);

        $whatsappPayload = null;
        if ($request->has('delivered_at') && !is_null($request->delivered_at)) {
            $whatsappPayload = $this->generateWhatsAppPayload('delivered', $post);
        }

        return response()->json([
            'message' => 'تم الحفظ',
            'data' => $post,
            'whatsapp_payload' => $whatsappPayload
        ]);
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
                    'manager_rejection_history' => $history,
                    'manager_approved_at' => null // تصفير التوقيت عند الرفض
                ]);
            } else {
                $post->update([
                    'manager_review_status' => 'معتمد',
                    'manager_approved_at' => now() // تسجيل توقيت موافقة المدير
                ]);
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
                    'rejection_history' => $history,
                    'department_approved_at' => null // تصفير التوقيت عند الرفض
                ]);
            }
            // --- حالة الموافقة ---
            else {
                $statuses[$userId] = 'معتمد'; // تسجيل موافقة هذا الشخص

                // إذا كان المتدخل هو المدير، يمكنه الموافقة عن الجميع بضغطة واحدة
                if ($isManager && !$isReviewer) {
                    $post->update([
                        'reviewers_statuses' => $statuses,
                        'review_status' => 'معتمد',
                        'department_approved_at' => now() // تسجيل توقيت موافقة القسم
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
                        'review_status' => $allApproved ? 'معتمد' : 'قيد الانتظار',
                        'department_approved_at' => $allApproved ? now() : null // يسجل الوقت فقط إذا اكتمل الإجماع
                    ]);
                }
            }
        }

        $whatsappPayload = null;
        if ($request->status === 'مرفوض') {
            $whatsappPayload = $this->generateWhatsAppPayload('rejected', $post);
        }

        return response()->json([
            'message' => 'تم تسجيل المراجعة بنجاح.',
            'data' => $post,
            'whatsapp_payload' => $whatsappPayload
        ]);
    }

    // 3. دالة إعادة الإرسال للمراجعة بعد الرفض والتعديل
    public function resubmit(Request $request, PlanPost $post)
    {
        $user = auth()->user();

        $isManager = $user->role->value === 'manager';

        // التحقق مما إذا كان المستخدم هو المنفذ المخصص
        $isExecutor = $user->id == $post->designer_id;

        // اللوجيك الأمني: المنفذ والمدير فقط هما من يحق لهما إعادة الإرسال
        if (!$isManager && !$isExecutor) {
            return response()->json([
                'message' => 'غير مصرح لك. فقط المنفذ المكلف بهذا المنشور أو المدير يمكنهم إعادة الإرسال.'
            ], 403);
        }

        $post->update([
            'delivered_at' => now(),                 // تحديث وقت التسليم للوقت الحالي
            'review_status' => 'قيد الانتظار',       // إرجاع حالة القسم لقيد الانتظار
            'manager_review_status' => 'قيد الانتظار', // إرجاع حالة المدير لقيد الانتظار
            'reviewers_statuses' => [],              // تفريغ تصويتات المراجعين لإجبارهم على المراجعة من جديد
            'department_approved_at' => null,        // تصفير توقيت اعتماد القسم
            'manager_approved_at' => null,           // تصفير توقيت اعتماد المدير
        ]);

        $whatsappPayload = $this->generateWhatsAppPayload('resubmitted', $post);

        return response()->json([
            'message' => 'تمت إعادة إرسال المنشور للمراجعة بنجاح.',
            'data' => $post,
            'whatsapp_payload' => $whatsappPayload
        ]);
    }

    // دالة بدء التنفيذ (التكليف) والتحقق من اكتمال الـ Brief
    public function startExecution(Request $request, PlanPost $post)
    {
        $user = auth()->user();
        $isManager = $user->role->value === 'manager';


        // التحقق مما إذا كان المستخدم هو مسئول الخطة
        $isResponsible = DB::table('content_plan_user')
            ->where('content_plan_id', $post->content_plan_id)
            ->where('user_id', $user->id)
            ->where('task_role', 'responsible')
            ->exists();

        if (!$isManager && !$isResponsible) {
            return response()->json([
                'message' => 'غير مصرح لك. فقط مسئول الخطة أو المدير يمكنهم تكليف المنفذ.'
            ], 403);
        }

        if (empty($post->designer_id)) {
            return response()->json([
                'message' => 'لا يمكن بدء العمل قبل اختيار "المنفذ" أولاً.'
            ], 422);
        }

        // قائمة الحقول الإلزامية التي تشكل الـ Brief للمنفذ
        $requiredFields = [
            'reviewer_ids' => 'المراجعين',
            'deadline' => 'الديدلاين',
            'finance_status' => 'موقف التمويل',
            'publishing_platform' => 'منصة النشر',
            'post_type' => 'نوع المنشور',
            'objective' => 'الهدف',
            'detailed_idea' => 'شرح الفكرة تفصيلياً',
            'caption' => 'Caption',
            'tov' => 'TOV',
            'call_to_action' => 'Call to Action',
            'hashtags' => 'Hashtag'
        ];

        $missingFields = [];

        foreach ($requiredFields as $field => $label) {
            // التحقق من المصفوفات (مثل المراجعين)
            if (is_array($post->$field)) {
                if (count($post->$field) === 0) {
                    $missingFields[] = $label;
                }
            }
            // التحقق من النصوص العادية
            elseif (empty(trim($post->$field ?? ''))) {
                $missingFields[] = $label;
            }
        }

        // إذا كان هناك حقول ناقصة، نرفض العملية ونخبر المسئول بها
        if (count($missingFields) > 0) {
            $missingString = implode('، ', $missingFields);
            return response()->json([
                'message' => 'لا يمكن بدء العمل قبل استكمال الـ Brief. الحقول الناقصة: ' . $missingString
            ], 422); // 422 تعني Unprocessable Entity (بيانات غير مكتملة)
        }

        $post->update(['execution_started_at' => now()]);

        $whatsappPayload = $this->generateWhatsAppPayload('execution_started', $post);

        return response()->json([
            'message' => 'تم إعطاء إشارة البدء للمنفذ بنجاح 🚀',
            'data' => $post,
            'whatsapp_payload' => $whatsappPayload
        ]);
    }

    // ==========================================
    // ---- دالة تجهيز إشعارات الواتساب (WhatsApp Payload)
    // ==========================================
    private function generateWhatsAppPayload($event, PlanPost $post)
    {
        $payload = [
            'message' => '',
            'recipients' => [], // مصفوفة لتخزين المستلمين (الاسم، الهاتف، الدور)
            'manager_phone' => null
        ];

        // 1. جلب رقم المدير (لاستخدامه كـ CC دائماً)
        // افترضنا أن طريقة التعرف على المدير هي أن الـ role يساوي 'manager'
        $manager = \App\Models\User::where('role', 'manager')->first();
        if ($manager && $manager->phone) {
            $payload['manager_phone'] = $manager->phone;
        }

        // 2. صياغة الرسالة وتحديد المستلمين بناءً على الحدث
        switch ($event) {
            case 'execution_started':
                $designer = \App\Models\User::find($post->designer_id);
                $payload['message'] = "🚀 *تكليف بمهمة جديدة*\nمرحباً {$designer?->name}،\nتم تكليفك بمنشور جديد وبدأ احتساب وقت التنفيذ.\n*الديدلاين:* " . ($post->deadline ? $post->deadline->format('Y-m-d H:i') : 'غير محدد') . "\nيرجى مراجعة لوحة العمل للبدء.";
                if ($designer && $designer->phone) {
                    $payload['recipients'][] = ['name' => $designer->name, 'phone' => $designer->phone, 'role' => 'المنفذ'];
                }
                break;

            case 'delivered':
                $reviewers = \App\Models\User::whereIn('id', $post->reviewer_ids ?? [])->get();
                $payload['message'] = "✅ *طلب مراجعة منشور*\nمرحباً،\nقام المنفذ بتسليم المنشور وهو جاهز الآن لمراجعتك.\n*روابط التسليم:* {$post->delivery_links}";
                foreach ($reviewers as $rev) {
                    if ($rev->phone) {
                        $payload['recipients'][] = ['name' => $rev->name, 'phone' => $rev->phone, 'role' => 'المراجع'];
                    }
                }
                break;

            case 'rejected':
                $designer = \App\Models\User::find($post->designer_id);
                $payload['message'] = "❌ *مطلوب تعديلات*\nمرحباً {$designer?->name}،\nتم رفض المنشور وإعادته إليك لتصحيح بعض الملاحظات.\nيرجى فتح اللوحة والاطلاع على (سجل الرفض) لمعرفة التفاصيل.";
                if ($designer && $designer->phone) {
                    $payload['recipients'][] = ['name' => $designer->name, 'phone' => $designer->phone, 'role' => 'المنفذ'];
                }
                break;

            case 'resubmitted':
                $reviewers = \App\Models\User::whereIn('id', $post->reviewer_ids ?? [])->get();
                $payload['message'] = "🔄 *إعادة إرسال للمراجعة*\nمرحباً،\nقام المنفذ بتصحيح الملاحظات وإعادة إرسال المنشور لمراجعته من جديد.\n*روابط التسليم:* {$post->delivery_links}";
                foreach ($reviewers as $rev) {
                    if ($rev->phone) {
                        $payload['recipients'][] = ['name' => $rev->name, 'phone' => $rev->phone, 'role' => 'المراجع'];
                    }
                }
                break;
        }

        return $payload;
    }

}

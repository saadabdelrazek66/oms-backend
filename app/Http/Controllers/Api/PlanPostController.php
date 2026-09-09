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

        // --- 3. حماية تعديل الروابط (للمدير فقط بعد إضافتها أول مرة) ---
        if ($request->has('published_links')) {
            $existingLinks = $post->published_links;
            $hasExistingLinks = is_array($existingLinks) && count(array_filter($existingLinks)) > 0;

            if ($hasExistingLinks && !$isManager) {
                return response()->json([
                    'message' => 'عذراً، الروابط مضافة مسبقاً. التعديل عليها مسموح للمدير فقط لحماية البيانات.'
                ], 403);
            }
        }

        // --- 4. حماية النشر (إجبار إدخال روابط لـ *جميع* المنصات المحددة) ---
        if ($request->has('actual_publish_status') && $request->actual_publish_status === 'تم النشر') {

            if ($post->review_status !== 'معتمد' || $post->manager_review_status !== 'معتمد') {
                return response()->json([
                    'message' => 'لا يمكن نشر المنشور قبل الحصول على موافقة جميع المراجعين واعتماد المدير النهائي.'
                ], 403);
            }

            $links = $request->has('published_links') ? $request->published_links : $post->published_links;
            $links = is_array($links) ? $links : [];

            $platforms = is_array($post->publishing_platform) ? $post->publishing_platform : json_decode($post->publishing_platform, true);
            $platforms = is_array($platforms) ? $platforms : [];

            $missingPlatforms = [];
            foreach ($platforms as $platform) {
                if (!isset($links[$platform]) || trim($links[$platform]) === '') {
                    $missingPlatforms[] = $platform;
                }
            }

            if (count($missingPlatforms) > 0) {
                $missingStr = implode('، ', $missingPlatforms);
                return response()->json([
                    'message' => "لا يمكن إتمام النشر! يرجى إضافة روابط المنصات التالية: {$missingStr}"
                ], 403);
            }
        }

        // --- 5. تجهيز البيانات وتطبيق القفل الذكي (Manager Override Lock) ---
        $data = $request->all();

        if (isset($data['review_status']) && $data['review_status'] === 'قيد الانتظار') {
            $data['department_approved_at'] = null; // تصفير وقت القسم
        }
        if (isset($data['manager_review_status']) && $data['manager_review_status'] === 'قيد الانتظار') {
            $data['manager_approved_at'] = null; // تصفير وقت المدير
        }

        // تعبئة الموديل بالبيانات الجديدة (في الذاكرة فقط) لالتقاط التغييرات
        $post->fill($data);

        // استخراج أسماء الحقول التي تغيرت قيمتها فعلياً
        $changedFields = array_keys($post->getDirty());

        // التأكد من أن locked_fields مصفوفة
        $lockedFields = is_array($post->locked_fields) ? $post->locked_fields : (json_decode($post->locked_fields, true) ?? []);

        if (!$isManager) {
            // الموظف: نتحقق إذا كان يحاول تعديل حقل تم قفله
            $attemptedToChangeLocked = array_intersect($changedFields, $lockedFields);

            if (!empty($attemptedToChangeLocked)) {
                return response()->json([
                    'message' => 'عذراً، لا يمكنك التعديل لأن المدير قام بتثبيت واعتماد بعض هذه الحقول مسبقاً 🔒'
                ], 403);
            }
        } else {
            // المدير: أي حقل يغيره، يتم إضافته فوراً لقائمة القفل
            if (!empty($changedFields)) {
                // استثناء حقول دورة العمل المتغيرة باستمرار من القفل الدائم
                $excludedFromLock = [
                    'review_status',
                    'manager_review_status',
                    'department_approved_at',
                    'manager_approved_at',
                    'actual_publish_status',
                    'delivered_at'
                ];

                $fieldsToLock = array_diff($changedFields, $excludedFromLock);
                $lockedFields = array_values(array_unique(array_merge($lockedFields, $fieldsToLock)));

                $post->locked_fields = $lockedFields;
            }
        }

        // رصد تغير حالة الرفض باستخدام getOriginal للبيانات المحفوظة مسبقاً
        $wasManagerRejected = $post->getOriginal('manager_review_status') === 'مرفوض';
        $isManagerRejecting = $post->manager_review_status === 'مرفوض';
        $shouldNotifyRejection = !$wasManagerRejected && $isManagerRejecting;

        // --- 6. حفظ التعديلات نهائياً ---
        $post->save(); // نستخدم save لأننا استخدمنا fill مسبقاً

        // --- 7. توليد حمولة الواتساب (WhatsApp Payload) ---
        $whatsappPayload = null;

        if ($request->has('delivered_at') && !is_null($request->delivered_at)) {
            $whatsappPayload = $this->generateWhatsAppPayload('delivered', $post);

        } elseif ($shouldNotifyRejection) {
            $targetUserIds = [];

            if ($post->designer_id) $targetUserIds[] = $post->designer_id;

            $reviewers = is_array($post->reviewer_ids) ? $post->reviewer_ids : (json_decode($post->reviewer_ids, true) ?? []);
            if (is_array($reviewers)) {
                $targetUserIds = array_merge($targetUserIds, $reviewers);
            }

            if ($post->responsible_id) {
                $targetUserIds[] = $post->responsible_id;
            } else {
                $post->loadMissing('contentPlan');
                if ($post->contentPlan && $post->contentPlan->responsible_id) {
                    $targetUserIds[] = $post->contentPlan->responsible_id;
                }
            }

            $targetUserIds = array_unique(array_filter($targetUserIds));

            $phoneNumbers = \App\Models\User::whereIn('id', $targetUserIds)
                ->whereNotNull('phone')
                ->pluck('phone')
                ->unique()
                ->toArray();

            $whatsappPayload = $this->generateWhatsAppPayload('rejected', $post, $phoneNumbers);
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

        // ... (باقي كود دالة المراجعة والـ update السابق كما هو) ...

        // --- توليد إشعارات الواتساب بناءً على النتيجة النهائية للعملية ---
        $whatsappPayload = null;

        if ($request->status === 'مرفوض') {
            // حالة الرفض (من المراجع أو المدير) ترسل رسالة تعديل للمنفذ
            $whatsappPayload = $this->generateWhatsAppPayload('rejected', $post);
        } else {
            // حالة الموافقة: نتحقق مما إذا كانت الموافقة أدت لاعتماد نهائي
            if ($request->review_type == 'manager' && $post->manager_review_status === 'معتمد') {
                // الاعتماد النهائي للمدير
                $whatsappPayload = $this->generateWhatsAppPayload('manager_approved', $post);
            }
            elseif ($request->review_type == 'reviewer' && $post->review_status === 'معتمد') {
                // اكتمال إجماع القسم (أو اعتماد إشرافي من المدير بالنيابة عن القسم)
                $whatsappPayload = $this->generateWhatsAppPayload('department_approved', $post);
            }
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
            'recipients' => [],
            'manager_phone' => null
        ];

        // 1. جلب رقم المدير (لاستخدامه كـ CC دائماً)
        $manager = \App\Models\User::where('role', 'manager')->first();
        if ($manager && $manager->phone) {
            $payload['manager_phone'] = $manager->phone;
        }

        // --- استعلامات مساعدة ---
        $designer = \App\Models\User::find($post->designer_id);

        $responsibleRecord = \Illuminate\Support\Facades\DB::table('content_plan_user')
            ->where('content_plan_id', $post->content_plan_id)
            ->where('task_role', 'responsible')
            ->first();
        $responsibleUser = $responsibleRecord ? \App\Models\User::find($responsibleRecord->user_id) : null;

        // --- تجهيز "مربع التفاصيل" الموحد لجميع الرسائل ---
        $planName = $post->contentPlan?->name ?? 'غير محدد';
        $postType = $post->post_type ?? 'غير محدد';

        // معالجة تاريخ النشر المخطط (أو الديدلاين كبديل إذا لم يكن متوفراً)
        $plannedDate = $post->target_date ?? $post->deadline;
        $plannedDateStr = 'غير محدد';
        if ($plannedDate) {
            $plannedDateStr = is_string($plannedDate) ? $plannedDate : $plannedDate->format('Y-m-d');
        }

        $postContext = "\n\n📌 *تفاصيل المنشور:*\n";
        $postContext .= "▪️ الخطة: {$planName}\n";
        $postContext .= "▪️ النوع: {$postType}\n";
        $postContext .= "▪️ الموعد المخطط: {$plannedDateStr}\n\n";

        // 2. صياغة الرسالة وتحديد المستلمين بناءً على الحدث
        switch ($event) {
            case 'execution_started':
                $payload['message'] = "🚀 *تكليف بمهمة جديدة*\nمرحباً {$designer?->name}،\nتم تكليفك بمنشور جديد وبدأ احتساب وقت التنفيذ." . $postContext . "يرجى مراجعة لوحة العمل للبدء.";
                if ($designer && $designer->phone) {
                    $payload['recipients'][] = ['name' => $designer->name, 'phone' => $designer->phone, 'role' => 'المنفذ'];
                }
                break;

            case 'delivered':
                $reviewers = \App\Models\User::whereIn('id', is_array($post->reviewer_ids) ? $post->reviewer_ids : (json_decode($post->reviewer_ids, true) ?? []))->get();
                $payload['message'] = "✅ *طلب مراجعة منشور*\nمرحباً،\nقام المنفذ بتسليم المنشور وهو جاهز الآن لمراجعتك." . $postContext . "*روابط التسليم:* {$post->delivery_links}";
                foreach ($reviewers as $rev) {
                    if ($rev->phone) {
                        $payload['recipients'][] = ['name' => $rev->name, 'phone' => $rev->phone, 'role' => 'المراجع'];
                    }
                }
                break;

            case 'rejected':
                $payload['message'] = "❌ *تنبيه رفض منشور*\nمرحباً،\nعذراً، تم رفض المنشور من قِبل المدير وإعادته لتصحيح الملاحظات." . $postContext . "يرجى من الأطراف المعنية (المنفذ والمراجعين) الاطلاع على (سجل الرفض) في اللوحة للبدء في التعديل فوراً.";

                $notifiedIds = [];
                if ($designer && $designer->phone) {
                    $payload['recipients'][] = ['name' => $designer->name, 'phone' => $designer->phone, 'role' => 'المنفذ'];
                    $notifiedIds[] = $designer->id;
                }

                $reviewerIds = is_array($post->reviewer_ids) ? $post->reviewer_ids : (json_decode($post->reviewer_ids, true) ?? []);
                if (count($reviewerIds) > 0) {
                    $reviewers = \App\Models\User::whereIn('id', $reviewerIds)->get();
                    foreach ($reviewers as $rev) {
                        if ($rev->phone && !in_array($rev->id, $notifiedIds)) {
                            $payload['recipients'][] = ['name' => $rev->name, 'phone' => $rev->phone, 'role' => 'المراجع'];
                            $notifiedIds[] = $rev->id;
                        }
                    }
                }

                if ($responsibleUser && $responsibleUser->phone && !in_array($responsibleUser->id, $notifiedIds)) {
                    $payload['recipients'][] = ['name' => $responsibleUser->name, 'phone' => $responsibleUser->phone, 'role' => 'مسئول الخطة'];
                }
                break;

            case 'resubmitted':
                $reviewers = \App\Models\User::whereIn('id', is_array($post->reviewer_ids) ? $post->reviewer_ids : (json_decode($post->reviewer_ids, true) ?? []))->get();
                $payload['message'] = "🔄 *إعادة إرسال للمراجعة*\nمرحباً،\nقام المنفذ بتصحيح الملاحظات وإعادة إرسال المنشور لمراجعته من جديد." . $postContext . "*روابط التسليم:* {$post->delivery_links}";
                foreach ($reviewers as $rev) {
                    if ($rev->phone) {
                        $payload['recipients'][] = ['name' => $rev->name, 'phone' => $rev->phone, 'role' => 'المراجع'];
                    }
                }
                break;

            case 'department_approved':
                $payload['message'] = "🎉 *تمت الموافقة المبدئية*\nمرحباً،\nتمت الموافقة على المنشور من قبل جميع المراجعين (موافقة القسم). وهو الآن في انتظار الاعتماد النهائي من المدير." . $postContext . "عمل رائع! 👏";
                if ($designer && $designer->phone) {
                    $payload['recipients'][] = ['name' => $designer->name, 'phone' => $designer->phone, 'role' => 'المنفذ'];
                }
                if ($responsibleUser && $responsibleUser->phone && $responsibleUser->id !== $designer?->id) {
                    $payload['recipients'][] = ['name' => $responsibleUser->name, 'phone' => $responsibleUser->phone, 'role' => 'مسئول الخطة'];
                }
                break;

            case 'manager_approved':
                $payload['message'] = "🌟 *تم الاعتماد النهائي*\nمرحباً،\nتم اعتماد المنشور نهائياً من قبل المدير وهو جاهز للنشر أو الجدولة." . $postContext . "تسلم إيديكم جميعاً! 🚀";
                if ($designer && $designer->phone) {
                    $payload['recipients'][] = ['name' => $designer->name, 'phone' => $designer->phone, 'role' => 'المنفذ'];
                }
                if ($responsibleUser && $responsibleUser->phone && $responsibleUser->id !== $designer?->id) {
                    $payload['recipients'][] = ['name' => $responsibleUser->name, 'phone' => $responsibleUser->phone, 'role' => 'مسئول الخطة'];
                }
                break;
        }

        return $payload;
    }


    // ==========================================
    // ---- دالة جلب مهام الموظفين (Workspace) مع الفلاتر الذكية والشارات
    // ==========================================
    public function getUserTasks(Request $request)
    {
        $user = auth()->user();
        $isManager = $user->role->value === 'manager';

        // --- 1. اللوجيك الأمني لتحديد الموظف المستهدف ---
        $targetUserId = ($isManager && $request->filled('user_id')) ? (int)$request->user_id : $user->id;

        // --- 2. بناء الاستعلام الأساسي للأدوار (مع جلب علاقة الخطة لاسمها) ---
        $query = PlanPost::with('contentPlan:id,name') // جلب اسم الخطة لتسهيل العرض
        ->where(function($q) use ($targetUserId) {
            $q->where('designer_id', $targetUserId)
                ->orWhereJsonContains('reviewer_ids', (string)$targetUserId)
                ->orWhereJsonContains('reviewer_ids', (int)$targetUserId);
        });

        // --- 3. تطبيق الفلاتر الشاملة (Filters) ---

        // أ. موقف التسليم
        if ($request->filled('delivery_status')) {
            if ($request->delivery_status === 'delivered') $query->whereNotNull('delivered_at');
            elseif ($request->delivery_status === 'pending') $query->whereNull('delivered_at');
        }

        // ب. حالة مراجعة القسم
        if ($request->filled('review_status')) {
            $query->where('review_status', $request->review_status);
        }

        // ج. حالة مراجعة المدير
        if ($request->filled('manager_review_status')) {
            $query->where('manager_review_status', $request->manager_review_status);
        }

        // د. حالة النشر الفعلي
        if ($request->filled('actual_publish_status')) {
            $query->where('actual_publish_status', $request->actual_publish_status);
        }

        // هـ. نوع المنشور
        if ($request->filled('post_type')) {
            $query->where('post_type', $request->post_type);
        }

        // و. خطة المحتوى
        if ($request->filled('content_plan_id')) {
            $query->where('content_plan_id', $request->content_plan_id);
        }

        // ز. فلتر النطاق الزمني للديدلاين (من - إلى)
        if ($request->filled('deadline_from')) {
            $query->whereDate('deadline', '>=', $request->deadline_from);
        }
        if ($request->filled('deadline_to')) {
            $query->whereDate('deadline', '<=', $request->deadline_to);
        }

        // --- 4. الترتيب الذكي (Smart Sorting) ---
        // الترتيب الافتراضي:
        // 1. المرفوض أولاً (لأنه يحتاج تعديل فوري)
        // 2. المهام التي لم تُسلم وتاريخها يقترب
        // 3. المهام المسلمة في الأسفل
        $query->orderByRaw("
            CASE
                WHEN manager_review_status = 'مرفوض' OR review_status = 'مرفوض' THEN 1
                WHEN delivered_at IS NULL THEN 2
                ELSE 3
            END
        ")
            ->orderByRaw('deadline IS NULL') // التي بلا ديدلاين في الأسفل
            ->orderBy('deadline', 'asc');

        // --- 5. التقسيم لصفحات (Pagination) ---
        $tasks = $query->paginate(12);

        // --- 6. جلب أسماء الأطراف بذكاء ---
        $userIds = collect();
        foreach ($tasks->items() as $task) {
            if ($task->designer_id) $userIds->push($task->designer_id);
            if (!empty($task->reviewer_ids) && is_array($task->reviewer_ids)) {
                foreach ($task->reviewer_ids as $rId) $userIds->push($rId);
            }
        }
        $userIds = $userIds->unique()->filter();
        $usersMap = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        // --- 7. حقن البيانات الإضافية والشارات الذكية (Smart Badges) ---
        $now = \Carbon\Carbon::now();

        $tasks->getCollection()->transform(function ($task) use ($usersMap, $now) {
            // إضافة الأسماء
            $task->designer_name = $usersMap[$task->designer_id] ?? 'غير معروف';
            $revNames = [];
            if (!empty($task->reviewer_ids) && is_array($task->reviewer_ids)) {
                foreach ($task->reviewer_ids as $rId) {
                    if (isset($usersMap[$rId])) $revNames[] = $usersMap[$rId];
                }
            }
            $task->reviewer_names = $revNames;

            // اسم الخطة
            $task->plan_name = $task->contentPlan ? $task->contentPlan->name : 'خطة غير محددة';

            // --- نظام الشارات الذكية (Badges) ---
            $badges = [];
            $isDelivered = !is_null($task->delivered_at);
            $deadline = $task->deadline ? \Carbon\Carbon::parse($task->deadline) : null;

            // شارة الرفض (الأهم)
            if ($task->manager_review_status === 'مرفوض' || $task->review_status === 'مرفوض') {
                $badges[] = ['type' => 'danger', 'text' => 'مرفوض ❌', 'color' => '#dc3545'];
            }
            // شارة الاعتماد النهائي
            elseif ($task->manager_review_status === 'معتمد') {
                $badges[] = ['type' => 'success', 'text' => 'معتمد 🌟', 'color' => '#198754'];
            }
            // شارة قيد المراجعة
            elseif ($isDelivered) {
                $badges[] = ['type' => 'info', 'text' => 'قيد المراجعة ⏳', 'color' => '#0dcaf0'];
            }
            // شارات المهام الجارية
            else {
                if ($deadline) {
                    if ($deadline->isPast()) {
                        $badges[] = ['type' => 'danger', 'text' => 'متأخر ⚠️', 'color' => '#dc3545'];
                    } elseif ($deadline->isToday()) {
                        $badges[] = ['type' => 'warning', 'text' => 'تسليم اليوم 🔔', 'color' => '#ffc107'];
                    }
                }
                // إذا تم إنشاؤها في آخر 48 ساعة ولم تُسلم بعد
                if ($task->created_at->diffInHours($now) <= 48) {
                    $badges[] = ['type' => 'primary', 'text' => 'مهمة جديدة ✨', 'color' => '#0d6efd'];
                }
            }

            $task->smart_badges = $badges;
            return $task;
        });

        return response()->json([
            'message' => 'تم جلب المهام بنجاح',
            'target_user_id' => $targetUserId,
            'is_manager' => $isManager,
            'tasks' => $tasks
        ]);
    }

    // ==========================================
    // ---- دالة حذف منشور (صف) - للمدير فقط
    // ==========================================
    public function destroy(PlanPost $post)
    {
        $user = auth()->user();

        // --- جدار الحماية: التأكد من أن المستخدم مدير ---
        // (تأكد من مطابقة طريقة فحص الدور لديك، إذا كانت $user->role فقط أو $user->role->value)
        if ($user->role->value !== 'manager') {
            return response()->json([
                'message' => 'غير مصرح لك بحذف المهام. هذه الصلاحية مخصصة للمدير فقط.'
            ], 403);
        }

        try {
            // تنفيذ عملية الحذف
            $post->delete();

            return response()->json([
                'message' => 'تم حذف الصف بنجاح.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء محاولة الحذف.'
            ], 500);
        }
    }

    // ==========================================
    // ---- دالة فك القفل عن حقل معين (للمدير فقط)
    // ==========================================
    public function unlockField(Request $request, PlanPost $post)
    {
        $user = auth()->user();

        // 1. حماية: المدير فقط من يملك المفتاح
        if ($user->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك بفك القفل.'], 403);
        }

        $fieldName = $request->input('field_name');
        if (!$fieldName) {
            return response()->json(['message' => 'اسم الحقل مطلوب.'], 400);
        }

        // 2. قراءة الحقول المقفولة الحالية
        $lockedFields = is_array($post->locked_fields) ? $post->locked_fields : (json_decode($post->locked_fields, true) ?? []);

        // 3. إزالة الحقل المستهدف من المصفوفة
        $lockedFields = array_values(array_filter($lockedFields, function($field) use ($fieldName) {
            return $field !== $fieldName;
        }));

        // 4. الحفظ
        $post->locked_fields = $lockedFields;
        // نستخدم save صريحة بدون إطلاق أحداث الـ update العادية حتى لا نُرسل إشعارات واتساب بالخطأ
        $post->saveQuietly();

        return response()->json([
            'message' => 'تم فك القفل بنجاح 🔓',
            'locked_fields' => $lockedFields
        ]);
    }
}

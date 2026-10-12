<?php

namespace App\Services;

use App\Models\PlanPost;
use App\Models\Task;
use App\Models\User;
use App\Models\PlanItemEstimate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkloadCascadingService
{
    /**
     * السقف اليومي لساعات العمل (8 ساعات)
     */
    const MAX_DAILY_HOURS = 8.0;

    /**
     * تحديد يوم العمل التالي (مع استبعاد يوم الجمعة كإجازة أسبوعية رسمية)
     */
    public function getNextWorkingDay(Carbon $date): Carbon
    {
        $next = $date->copy()->addDay()->startOfDay();

        // يوم الجمعة هو اليوم رقم 5 في Carbon (Friday)
        while ($next->isFriday()) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * حساب الساعات التقديرية لمنشور الخطة بناءً على جدول التقديرات أو نوع المنشور
     */
    public function getPostEstimatedHours(PlanPost $post): float
    {
        if ($post->estimated_hours && $post->estimated_hours > 0) {
            return (float) $post->estimated_hours;
        }

        if (!empty($post->post_type)) {
            $trimmedType = trim($post->post_type);
            $estimate = PlanItemEstimate::where('name', 'like', '%' . $trimmedType . '%')->first();
            if ($estimate && $estimate->estimated_hours > 0) {
                return (float) $estimate->estimated_hours;
            }

            $typeLower = strtolower($trimmedType);

            if (str_contains($typeLower, 'video') || str_contains($typeLower, 'reels') || str_contains($typeLower, 'shorts') || str_contains($post->post_type, 'فيديو')) {
                return 4.0;
            }

            if (str_contains($typeLower, 'carousel') || str_contains($post->post_type, 'كاروسيل')) {
                return 2.5;
            }

            if (str_contains($typeLower, 'motion') || str_contains($post->post_type, 'موشن')) {
                return 5.0;
            }

            if (str_contains($typeLower, 'story') || str_contains($post->post_type, 'ستوري')) {
                return 1.0;
            }

            if (str_contains($typeLower, 'static') || str_contains($typeLower, 'design') || str_contains($post->post_type, 'تصميم') || str_contains($post->post_type, 'بوست')) {
                return 2.0;
            }
        }

        return 2.0;
    }

    /**
     * حساب الساعات التقديرية لمهمة المشروع
     */
    public function getTaskEstimatedHours(Task $task): float
    {
        if ($task->estimated_hours && $task->estimated_hours > 0) {
            return (float) $task->estimated_hours;
        }

        return 2.0;
    }

    /**
     * جلب جدول أعمال الموظف ليوم محدد وحساب إجمالي الساعات
     * ملاحظة: يتم استبعاد المنشورات المسلمة للمراجعة بالفعل (delivered_at) ما لم تكن مرفوضة
     */
    public function getEmployeeDaySchedule(int $userId, string $dateString): array
    {
        $targetDate = Carbon::parse($dateString)->toDateString();

        // 1. جلب منشورات الخطط غير المسلمة بعد أو المرفوضة المطلوب تعديلها
        $planPosts = PlanPost::with(['plan.client'])
            ->where('designer_id', $userId)
            ->whereNull('final_delivered_at')
            ->where(function ($q) {
                $q->whereNull('delivered_at')
                  ->orWhere('manager_review_status', 'مرفوض');
            })
            ->where(function ($q) {
                $q->whereNull('actual_publish_status')
                  ->orWhere('actual_publish_status', '!=', 'تم النشر');
            })
            ->where(function ($q) use ($targetDate) {
                $q->whereDate('deadline', $targetDate)
                  ->orWhere(function ($sub) use ($targetDate) {
                      $sub->whereNull('deadline')
                          ->whereDate('target_date', $targetDate);
                  });
            })
            ->get();

        // 2. جلب مهام المشاريع العامة
        $tasks = Task::with('project')
            ->where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', $targetDate)
            ->get();

        // 3. حساب إجمالي الساعات
        $totalHours = 0.0;
        foreach ($planPosts as $post) {
            $totalHours += $this->getPostEstimatedHours($post);
        }
        foreach ($tasks as $task) {
            $totalHours += $this->getTaskEstimatedHours($task);
        }

        return [
            'date' => $targetDate,
            'total_hours' => round($totalHours, 2),
            'plan_posts' => $planPosts,
            'tasks' => $tasks,
        ];
    }

    /**
     * تنفيذ الإزاحة والترحيل التلقائي المتتالي (Domino Cascading)
     */
    public function autoCascadeWorkload(int $userId, Carbon $startDate, $urgentItem, string $urgentTitle, ?string $urgentType = null): array
    {
        return DB::transaction(function () use ($userId, $startDate, $urgentItem, $urgentTitle, $urgentType) {
            // 🔒 قفل تشغيلي متزامن على سجل الموظف لمنع تضارب العمليات المتزامنة (Race Conditions)
            User::where('id', $userId)->lockForUpdate()->first();

            $displacedItems = [];
            $currentDate = $startDate->copy()->startOfDay();

            if (is_object($urgentItem)) {
                $urgentId = $urgentItem->id;
                $urgentType = ($urgentItem instanceof PlanPost) ? 'plan_post' : 'task';
            } else {
                $urgentId = $urgentItem;
                $urgentType = $urgentType ?: 'plan_post';
            }

            // حد أقصى 14 يوم عمل متتالي لمنع الحلقات اللانهائية
            $maxIterations = 14;
            $iteration = 0;
            $safetyWarning = null;

            while ($iteration < $maxIterations) {
                $iteration++;
                $dateString = $currentDate->toDateString();
                $schedule = $this->getEmployeeDaySchedule($userId, $dateString);

                // إذا كانت ساعات اليوم في الحدود المسموحة (<= 8 ساعات)، يتوقف الترحيل فوراً
                if ($schedule['total_hours'] <= self::MAX_DAILY_HOURS) {
                    break;
                }

                // حساب اليوم التالي (مع مراعاة تخطي الجمعة)
                $nextWorkingDay = $this->getNextWorkingDay($currentDate);

                // إزاحة المهام الفائضة من هذا اليوم إلى اليوم التالي حتى تعود ساعات اليوم الحالي <= 8 ساعات
                $anyDisplacedForThisDay = false;
                while ($schedule['total_hours'] > self::MAX_DAILY_HOURS) {
                    $candidate = $this->selectDisplacementCandidate($schedule, $urgentId, $urgentType, $currentDate, $nextWorkingDay);
                    if (!$candidate) {
                        break;
                    }

                    $displacedInfo = $this->displaceSingleItem($candidate, $nextWorkingDay, $urgentTitle, $urgentId, $urgentType);
                    if ($displacedInfo) {
                        $displacedItems[] = $displacedInfo;
                        $anyDisplacedForThisDay = true;
                    }

                    // تحديث جدول اليوم الحالي بعد إزاحة هذا العنصر لفحص ما إذا كان ما زال متجاوزاً
                    $schedule = $this->getEmployeeDaySchedule($userId, $dateString);
                }

                // 🛡️ [فحص أسباب تعذر الترحيل وتوليد رسالة أمان دقيقة]:
                if ($schedule['total_hours'] > self::MAX_DAILY_HOURS && !$anyDisplacedForThisDay) {
                    $allRemainingAreUrgent = $schedule['plan_posts']->every(fn($p) => $p->is_urgent) && $schedule['tasks']->every(fn($t) => $t->is_urgent);
                    if ($allRemainingAreUrgent) {
                        $safetyWarning = "⚠️ تنبيه أمان: جدول يوم ({$dateString}) يتجاوز 8 ساعات عمل، وتعذر الترحيل لأن كافة المهام والمنشورات المتبقية في هذا اليوم مصنفة كمهام طارئة (Urgent)!";
                    } else {
                        $safetyWarning = "⚠️ تنبيه أمان: تعذر ترحيل بعض مهام يوم ({$dateString}) لأنها مقيدة بمواعيد نشر مجدولة مع العملاء أو مواعيد انتهاء مشاريع قريبة جداً!";
                    }
                    break;
                }

                if (!$anyDisplacedForThisDay) {
                    // لا توجد مهام قابلة للإزاحة، نتوقف
                    break;
                }

                // الانتقال لليوم التالي لفحص ما إذا كانت الإزاحات الجديدة تسببت في تجاوزه للـ 8 ساعات (تأثير الدومينو)
                $currentDate = $nextWorkingDay;
            }

            return [
                'displaced_items' => $displacedItems,
                'warning' => $safetyWarning,
            ];
        });
    }

    /**
     * تنفيذ الإرجاع الذكي للمهام والمنشورات المرحّلة (Auto-Rollback)
     * يعيد المنشورات المرحّلة إلى موعدها الأصلي بمجرد فك صفة العاجل وتوفر الساعات في اليوم
     */
    public function autoRollbackWorkload(int $userId, $urgentItem = null, string $urgentTitle = '', ?string $urgentType = null): array
    {
        return DB::transaction(function () use ($userId, $urgentItem, $urgentTitle, $urgentType) {
            // 🔒 قفل تشغيلي متزامن على سجل الموظف
            User::where('id', $userId)->lockForUpdate()->first();

            $rolledBackItems = [];

            if (is_object($urgentItem)) {
                $urgentId = $urgentItem->id;
                $urgentType = ($urgentItem instanceof PlanPost) ? 'plan_post' : (($urgentItem instanceof Task) ? 'task' : null);
            } else {
                $urgentId = $urgentItem;
                $urgentType = $urgentType ?: null;
            }

            $urgentTag = ($urgentType && $urgentId) ? "[{$urgentType}:{$urgentId}]" : null;

            $now = Carbon::now();
            $maxPasses = 10; // عدد دورات كافٍ لإرجاع كافة الترحيلات المتسلسلة
            $pass = 0;

            do {
                $pass++;
                $anyRolledBackThisPass = false;

                // 1. جلب منشورات الخطط المرحّلة للموظف
                $displacedPosts = PlanPost::where('designer_id', $userId)
                    ->where('is_displaced', true)
                    ->whereNotNull('original_deadline')
                    ->whereNull('delivered_at')
                    ->whereNull('final_delivered_at')
                    ->where(function ($q) {
                        $q->whereNull('actual_publish_status')
                          ->orWhere('actual_publish_status', '!=', 'تم النشر');
                    })
                    ->get();

                // 2. جلب مهام المشاريع المرحّلة للموظف
                $displacedTasks = Task::where('assigned_to', $userId)
                    ->where('is_displaced', true)
                    ->whereNotNull('original_due_date')
                    ->where('status', '!=', 'completed')
                    ->get();

                if ($displacedPosts->isEmpty() && $displacedTasks->isEmpty()) {
                    break;
                }

                // تجميع العناصر المرشحة
                $candidates = collect();

                foreach ($displacedPosts as $post) {
                    $origDateTime = Carbon::parse($post->original_deadline);
                    $origDate = $origDateTime->copy()->startOfDay();
                    $isDirectlyDisplacedByThis = false;
                    if ($urgentTag && str_contains($post->displaced_reason ?? '', $urgentTag)) {
                        $isDirectlyDisplacedByThis = true;
                    } elseif (!$urgentTag && !empty($urgentTitle) && str_contains($post->displaced_reason ?? '', trim($urgentTitle))) {
                        $isDirectlyDisplacedByThis = true;
                    }

                    $candidates->push([
                        'item' => $post,
                        'type' => 'plan_post',
                        'original_datetime' => $origDateTime,
                        'original_date' => $origDate,
                        'current_date' => $post->deadline ? Carbon::parse($post->deadline) : null,
                        'hours' => $this->getPostEstimatedHours($post),
                        'is_direct' => $isDirectlyDisplacedByThis,
                    ]);
                }

                foreach ($displacedTasks as $task) {
                    $origDateTime = Carbon::parse($task->original_due_date);
                    $origDate = $origDateTime->copy()->startOfDay();
                    $isDirectlyDisplacedByThis = false;
                    if ($urgentTag && str_contains($task->displaced_reason ?? '', $urgentTag)) {
                        $isDirectlyDisplacedByThis = true;
                    } elseif (!$urgentTag && !empty($urgentTitle) && str_contains($task->displaced_reason ?? '', trim($urgentTitle))) {
                        $isDirectlyDisplacedByThis = true;
                    }

                    $candidates->push([
                        'item' => $task,
                        'type' => 'task',
                        'original_datetime' => $origDateTime,
                        'original_date' => $origDate,
                        'current_date' => $task->due_date ? Carbon::parse($task->due_date) : null,
                        'hours' => $this->getTaskEstimatedHours($task),
                        'is_direct' => $isDirectlyDisplacedByThis,
                    ]);
                }

                // ترتيب الأولويات: المباشرة أولاً ثم الأقدم في التاريخ الأصلي
                $sortedCandidates = $candidates->sort(function ($a, $b) {
                    if ($a['is_direct'] && !$b['is_direct']) return -1;
                    if (!$a['is_direct'] && $b['is_direct']) return 1;
                    return $a['original_datetime']->timestamp <=> $b['original_datetime']->timestamp;
                });

                foreach ($sortedCandidates as $cand) {
                    $origDateTime = $cand['original_datetime'];
                    $origDate = $cand['original_date'];

                    // 🛡️ حماية: لا نقوم بإرجاع الديدلاين إلى وقت انقضى في الماضي (حتى لو كان في نفس اليوم)
                    if ($origDateTime->lt($now)) {
                        continue;
                    }

                    $dateString = $origDate->toDateString();
                    $schedule = $this->getEmployeeDaySchedule($userId, $dateString);
                    $neededHours = $cand['hours'];

                    // 🛡️ السقف الصارم: فحص توفر الساعات في اليوم الأصلي دون تجاوز الـ 8 ساعات تحت أي ظرف
                    $canFit = (($schedule['total_hours'] + $neededHours) <= self::MAX_DAILY_HOURS);

                    if ($canFit) {
                        $item = $cand['item'];
                        $oldCurrentDateStr = $cand['current_date'] ? $cand['current_date']->toDateString() : null;

                        if ($cand['type'] === 'plan_post') {
                            $item->deadline = $item->original_deadline;
                            $item->original_deadline = null;
                            $item->is_displaced = false;
                            $item->displaced_reason = null;
                            $item->save();

                            $rolledBackItems[] = [
                                'type' => 'plan_post',
                                'id' => $item->id,
                                'title' => $item->post_type ? "منشور {$item->post_type}" : "منشور خطة #{$item->id}",
                                'from_date' => $oldCurrentDateStr,
                                'to_date' => $dateString,
                                'hours' => $neededHours,
                            ];
                        } else {
                            $item->due_date = $item->original_due_date;
                            $item->original_due_date = null;
                            $item->is_displaced = false;
                            $item->displaced_reason = null;
                            $item->save();

                            $rolledBackItems[] = [
                                'type' => 'task',
                                'id' => $item->id,
                                'title' => $item->title ?? "مهمة مشروع #{$item->id}",
                                'from_date' => $oldCurrentDateStr,
                                'to_date' => $dateString,
                                'hours' => $neededHours,
                            ];
                        }

                        $anyRolledBackThisPass = true;
                        // بعد إرجاع عنصر، نخرج من الحلقة الداخلية لإعادة حساب الجداول بالبيانات الجديدة
                        break;
                    }
                }

            } while ($anyRolledBackThisPass && $pass < $maxPasses);

            return [
                'rolled_back_items' => $rolledBackItems,
            ];
        });
    }

    /**
     * اختيار العنصر المرشح للإزاحة من اليوم المزدحم
     * الأولوية:
     * 1. المهام العامة منخفضة الأولوية (Low Priority) لإفساح المجال لبوستات العملاء الرسمية (مع حماية تاريخ نهاية المشروع)
     * 2. منشورات الخطط ذات الأفق الأبعد عن موعد النشر (أكبر هامش أمان) وتوزيع الترحيل بعدالة
     * 3. باقي مهام المشاريع العامة (مع حماية تاريخ نهاية المشروع)
     */
    protected function selectDisplacementCandidate(array $schedule, $urgentId, string $urgentType, Carbon $currentDate, Carbon $nextWorkingDay)
    {
        // 1. تصفية منشورات الخطط المؤهلة
        $eligiblePosts = $schedule['plan_posts']->filter(function ($p) use ($urgentId, $urgentType, $nextWorkingDay) {
            if ($p->is_urgent) return false;
            if ($urgentType === 'plan_post' && $p->id == $urgentId) return false;

            // 🛡️ [قفل أمان موعد النشر]: التسليم يجب أن يسبق يوم النشر المجدول بيوم عمل كامل على الأقل!
            if ($p->target_date) {
                $publishDate = Carbon::parse($p->target_date)->startOfDay();
                if ($nextWorkingDay->startOfDay()->gte($publishDate)) {
                    return false;
                }
            } else {
                // إذا لم يكن هناك تاريخ نشر مسجل، نمنع الترحيل المتتالي إذا ابتعد عن الديدلاين الأصلي بأكثر من 3 أيام عمل
                if ($p->is_displaced && $p->original_deadline) {
                    $orig = Carbon::parse($p->original_deadline)->startOfDay();
                    if ($nextWorkingDay->diffInWeekdays($orig) >= 3) {
                        return false;
                    }
                }
            }

            return true;
        });

        // 2. تصفية مهام المشاريع المؤهلة مع حماية موعد نهاية المشروع
        $eligibleTasks = $schedule['tasks']->filter(function ($t) use ($urgentId, $urgentType, $nextWorkingDay) {
            if ($t->is_urgent) return false;
            if ($urgentType === 'task' && $t->id == $urgentId) return false;

            // 🛡️ [قفل أمان موعد انتهاء المشروع]: لا يجوز ترحيل موعد المهمة لما بعد تاريخ انتهاء المشروع!
            if ($t->project && $t->project->end_date) {
                $projectEnd = Carbon::parse($t->project->end_date)->endOfDay();
                if ($nextWorkingDay->startOfDay()->gt($projectEnd)) {
                    return false; // يُستبعد لحماية الإطار الزمني للمشروع
                }
            }

            return true;
        });

        // 🌟 أولاً: إزاحة المهام العامة منخفضة الأولوية أولاً لحماية مواعيد نشر منشورات العملاء
        $lowPriorityTasks = $eligibleTasks->filter(fn($t) => ($t->priority ?? 'medium') === 'low');
        if ($lowPriorityTasks->isNotEmpty()) {
            return $lowPriorityTasks->first();
        }

        // 🌟 ثانياً: منشورات الخطط - نرجح المنشور الذي لم يتم ترحيله بعد (لمنع ظاهرة كرة الثلج) ثم الأبعد نشراً
        if ($eligiblePosts->isNotEmpty()) {
            return $eligiblePosts->sort(function ($a, $b) {
                // توزيع عادل: المنشور الذي لم يسبق ترحيله يرحل أولاً بدلاً من تكرار ترحيل نفس المنشور
                if ($a->is_displaced && !$b->is_displaced) return 1;
                if (!$a->is_displaced && $b->is_displaced) return -1;

                $aPub = $a->target_date ? Carbon::parse($a->target_date)->timestamp : PHP_INT_MAX;
                $bPub = $b->target_date ? Carbon::parse($b->target_date)->timestamp : PHP_INT_MAX;
                if ($aPub !== $bPub) {
                    return $bPub <=> $aPub; // الأبعد نشراً يُرحل أولاً
                }

                // عند التساوي، نختار المنشور الأقل وقتاً
                return $this->getPostEstimatedHours($a) <=> $this->getPostEstimatedHours($b);
            })->first();
        }

        // 🌟 ثالثاً: باقي مهام المشاريع العامة (المتوسطة ثم العالية)
        if ($eligibleTasks->isNotEmpty()) {
            $priorityOrder = ['low' => 1, 'medium' => 2, 'high' => 3];
            return $eligibleTasks->sortBy(function ($t) use ($priorityOrder) {
                return $priorityOrder[$t->priority ?? 'medium'] ?? 2;
            })->first();
        }

        return null;
    }

    protected function displaceSingleItem($item, Carbon $newDate, string $urgentTitle, $urgentId = null, string $urgentType = ''): ?array
    {
        $tag = ($urgentType && $urgentId) ? "[{$urgentType}:{$urgentId}] " : '';

        if ($item instanceof PlanPost) {
            $oldDeadline = $item->deadline ? Carbon::parse($item->deadline) : ($item->target_date ? Carbon::parse($item->target_date)->setTime(17, 0) : null);
            
            if (!$item->is_displaced) {
                $item->original_deadline = $oldDeadline;
            }

            // ضبط الوقت الجديد على نفس توقيت الديدلاين أو 5:00 مساءً
            $newDateTime = $newDate->copy()->setTime(
                $oldDeadline ? $oldDeadline->hour : 17,
                $oldDeadline ? $oldDeadline->minute : 0
            );

            // ترحيل موعد التسليم الابتدائي (deadline) فقط!
            // لا نعدل تاريخ النشر المخطط (target_date) إطلاقاً، لأن النشر مجدول مسبقاً والعمل يسبق النشر بوقت كافٍ
            $item->deadline = $newDateTime;
            $item->is_displaced = true;
            $item->displaced_reason = "تم ترحيل موعد التسليم تلقائياً لإفساح المجال للمهمة الطارئة {$tag}: {$urgentTitle}";
            $item->save();

            return [
                'type' => 'plan_post',
                'id' => $item->id,
                'title' => $item->post_type ? "منشور {$item->post_type}" : "منشور خطة #{$item->id}",
                'old_date' => $oldDeadline ? $oldDeadline->toDateString() : null,
                'new_date' => $newDate->toDateString(),
                'hours' => $this->getPostEstimatedHours($item),
            ];
        }

        if ($item instanceof Task) {
            $oldDueDate = $item->due_date ? Carbon::parse($item->due_date) : null;

            if (!$item->is_displaced) {
                $item->original_due_date = $oldDueDate;
            }

            $newDateTime = $newDate->copy()->setTime(
                $oldDueDate ? $oldDueDate->hour : 17,
                $oldDueDate ? $oldDueDate->minute : 0
            );

            $item->due_date = $newDateTime;
            $item->is_displaced = true;
            $item->displaced_reason = "تم الترحيل تلقائياً لإفساح المجال للمهمة الطارئة {$tag}: {$urgentTitle}";
            $item->save();

            return [
                'type' => 'task',
                'id' => $item->id,
                'title' => $item->title ?? "مهمة مشروع #{$item->id}",
                'old_date' => $oldDueDate ? $oldDueDate->toDateString() : null,
                'new_date' => $newDate->toDateString(),
                'hours' => $this->getTaskEstimatedHours($item),
            ];
        }

        return null;
    }
}

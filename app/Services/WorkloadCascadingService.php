<?php

namespace App\Services;

use App\Models\PlanPost;
use App\Models\Task;
use App\Models\User;
use App\Models\PlanItemEstimate;
use App\Models\TaskWorkloadAllocation;
use App\Modules\QuickTasks\Models\QuickTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkloadCascadingService
{
    /**
     * السقف الافتراضي لساعات العمل (8 ساعات)
     */
    const MAX_DAILY_HOURS = 8.0;

    /**
     * الحصول على سعة ساعات العمل اليومية المعتمدة للموظف
     */
    public function getUserDailyCapacity($userOrId = null): float
    {
        if ($userOrId instanceof User) {
            return $userOrId->getDailyCapacity();
        }

        if (is_numeric($userOrId)) {
            $user = User::find($userOrId);
            if ($user) {
                return $user->getDailyCapacity();
            }
        }

        try {
            return (float) app(\App\Services\SettingService::class)->get('default_daily_hours', 8.0);
        } catch (\Throwable $e) {
            return self::MAX_DAILY_HOURS;
        }
    }

    /**
     * تحديد يوم العمل التالي للموظف (مع استبعاد أيام الإجازة الأسبوعية المحددة له)
     */
    public function getNextWorkingDay(Carbon $date, $userOrId = null): Carbon
    {
        $user = null;
        if ($userOrId instanceof User) {
            $user = $userOrId;
        } elseif (is_numeric($userOrId)) {
            $user = User::find($userOrId);
        }

        if ($user) {
            return $user->getNextWorkingDay($date);
        }

        $next = $date->copy()->addDay()->startOfDay();
        $safety = 0;
        try {
            $weekendDays = app(\App\Services\SettingService::class)->get('weekend_days', ['friday']);
        } catch (\Throwable $e) {
            $weekendDays = ['friday'];
        }
        $weekendDaysLower = array_map('strtolower', (array) $weekendDays);

        while (in_array(strtolower($next->format('l')), $weekendDaysLower) && $safety < 14) {
            $next->addDay();
            $safety++;
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
     * حساب الساعات التقديرية للمهمة السريعة
     */
    public function getQuickTaskEstimatedHours(QuickTask $quickTask): float
    {
        if ($quickTask->estimated_hours && $quickTask->estimated_hours > 0) {
            return (float) $quickTask->estimated_hours;
        }

        return 1.0;
    }

    /**
     * حساب الساعات التقديرية لأي عنصر عام
     */
    public function getItemEstimatedHours($item, string $type): float
    {
        if ($type === 'plan_post' || $item instanceof PlanPost) {
            return $this->getPostEstimatedHours($item);
        }
        if ($type === 'task' || $item instanceof Task) {
            return $this->getTaskEstimatedHours($item);
        }
        if ($type === 'quick_task' || $item instanceof QuickTask) {
            return $this->getQuickTaskEstimatedHours($item);
        }
        return 2.0;
    }

    /**
     * فحص ما إذا كانت المهمة مكتملة بالفعل لمنع تشفيتها (مشاريع، بوستات، مهام سريعة)
     */
    public function isItemCompleted($item, string $type): bool
    {
        if (!$item) return false;

        if ($type === 'plan_post' || $item instanceof PlanPost) {
            // إذا كان المنشور مرفوضاً من القسم أو المدير، فهو غير مكتمل ويحتاج تعديل
            if ($item->manager_review_status === 'مرفوض' || $item->review_status === 'مرفوض') {
                return false;
            }
            if (!empty($item->final_delivered_at) || !empty($item->delivered_at)) {
                return true;
            }
            $status = strtolower($item->status ?? '');
            $publishStatus = strtolower($item->actual_publish_status ?? '');
            return in_array($status, ['completed', 'published', 'مكتمل', 'تم النشر']) ||
                   in_array($publishStatus, ['published', 'تم النشر']) ||
                   $item->manager_review_status === 'معتمد';
        }

        if ($type === 'task' || $item instanceof Task) {
            $status = strtolower($item->status ?? '');
            return in_array($status, ['completed', 'مكتمل', 'done']);
        }

        if ($type === 'quick_task' || $item instanceof QuickTask) {
            $status = strtolower($item->status ?? '');
            return in_array($status, ['completed', 'مكتمل', 'done']);
        }

        return false;
    }

    /**
     * جلب سجل المهمة بناءً على نوعها ومعرفها
     */
    public function findTaskItem(string $type, int $id)
    {
        switch ($type) {
            case 'plan_post':
                return PlanPost::with(['plan.client'])->find($id);
            case 'task':
                return Task::with('project')->find($id);
            case 'quick_task':
                return QuickTask::with(['creator', 'assignee'])->find($id);
            default:
                return null;
        }
    }

    /**
     * عنوان المهمة المناسب للعرض
     */
    public function getItemTitle($item, string $type): string
    {
        if ($type === 'plan_post' || $item instanceof PlanPost) {
            $clientName = $item->plan?->client?->name ?? '';
            $prefix = $item->post_type ? "منشور {$item->post_type}" : "منشور خطة #{$item->id}";
            return $clientName ? "{$prefix} ({$clientName})" : $prefix;
        }
        if ($type === 'task' || $item instanceof Task) {
            return $item->title ?? "مهمة مشروع #{$item->id}";
        }
        if ($type === 'quick_task' || $item instanceof QuickTask) {
            return $item->title ?? "مهمة سريعة #{$item->id}";
        }
        return "مهمة #{$item->id}";
    }

    /**
     * جلب جدول أعمال الموظف ليوم محدد وحساب إجمالي الساعات بناءً على نظام الساعات والتشفيت الجزئي
     */
    public function getEmployeeDaySchedule(int $userId, string $dateString): array
    {
        $targetDate = Carbon::parse($dateString)->toDateString();

        // 1. جلب التخصيصات المسجلة صراحة لهذا اليوم في جدول task_workload_allocations
        $dayAllocations = TaskWorkloadAllocation::where('user_id', $userId)
            ->whereDate('work_date', $targetDate)
            ->get()
            ->keyBy(fn($a) => "{$a->allocatable_type}_{$a->allocatable_id}");

        // 2. جلب منشورات الخطط غير المسلمة أو المرفوضة المطلوب تعديلها
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

        // 3. جلب مهام المشاريع العامة
        $tasks = Task::with('project')
            ->where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', $targetDate)
            ->get();

        // 4. جلب المهام السريعة
        $quickTasks = QuickTask::with(['creator', 'assignee'])
            ->where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->whereDate('deadline', $targetDate)
            ->get();

        // 5. دمج المهام التي لديها تخصيص صريح في هذا اليوم حتى لو كان تاريخها الأصلي مختلفاً (ساعات مرحلة إلى اليوم)
        foreach ($dayAllocations as $alloc) {
            $type = $alloc->allocatable_type;
            $id = (int) $alloc->allocatable_id;

            if ($type === 'plan_post' && !$planPosts->contains('id', $id)) {
                $post = PlanPost::with(['plan.client'])->find($id);
                if ($post && !$post->final_delivered_at) {
                    $planPosts->push($post);
                }
            } elseif ($type === 'task' && !$tasks->contains('id', $id)) {
                $task = Task::with('project')->find($id);
                if ($task && $task->status !== 'completed') {
                    $tasks->push($task);
                }
            } elseif ($type === 'quick_task' && !$quickTasks->contains('id', $id)) {
                $qt = QuickTask::with(['creator', 'assignee'])->find($id);
                if ($qt && $qt->status !== 'completed') {
                    $quickTasks->push($qt);
                }
            }
        }

        // 6. ربط بيانات الساعات اليومية والمرحلة لكل عنصر وحساب إجمالي الساعات
        $totalHours = 0.0;

        $attachWorkloadMetadata = function ($item, string $type) use ($targetDate, $dayAllocations, &$totalHours) {
            $totalEst = $this->getItemEstimatedHours($item, $type);
            $allocKey = "{$type}_{$item->id}";
            $alloc = $dayAllocations->get($allocKey);

            if ($alloc) {
                $todayHours = (float) $alloc->allocated_hours;
                $isShifted = (bool) $alloc->is_shifted;
                $shiftedFrom = $alloc->shifted_from_date ? Carbon::parse($alloc->shifted_from_date)->toDateString() : null;
                $shiftReason = $alloc->shift_reason;
            } else {
                // فحص ما إذا كان هناك ساعات مرحلة إلى أيام أخرى من هذه المهمة
                $otherAllocSum = (float) TaskWorkloadAllocation::where('allocatable_type', $type)
                    ->where('allocatable_id', $item->id)
                    ->whereDate('work_date', '!=', $targetDate)
                    ->sum('allocated_hours');

                $todayHours = max(0.0, round($totalEst - $otherAllocSum, 2));
                $isShifted = (bool) $item->is_displaced;
                $shiftedFrom = null;
                $shiftReason = $item->displaced_reason;
            }

            // فحص إجمالي الساعات المرحلة إلى المستقبل من هذه المهمة
            $shiftedToFuture = (float) TaskWorkloadAllocation::where('allocatable_type', $type)
                ->where('allocatable_id', $item->id)
                ->whereDate('work_date', '>', $targetDate)
                ->sum('allocated_hours');

            $item->allocated_hours_today = round($todayHours, 2);
            $item->total_estimated_hours = round($totalEst, 2);
            $item->shifted_hours_to_future = round($shiftedToFuture, 2);
            $item->is_partially_shifted = ($shiftedToFuture > 0);
            $item->is_shifted_to_today = ($shiftedFrom !== null);
            $item->shifted_from_date = $shiftedFrom;
            $item->shift_reason = $shiftReason;
            $item->task_type = $type;
            $item->is_completed = $this->isItemCompleted($item, $type);

            $totalHours += $todayHours;
            return $item;
        };

        // تصفية العناصر التي ليس لها ساعات اليوم (إذا تم تشفيتها بالكامل إلى يوم آخر)
        $processedPosts = $planPosts->map(fn($p) => $attachWorkloadMetadata($p, 'plan_post'))->filter(fn($p) => $p->allocated_hours_today > 0)->values();
        $processedTasks = $tasks->map(fn($t) => $attachWorkloadMetadata($t, 'task'))->filter(fn($t) => $t->allocated_hours_today > 0)->values();
        $processedQuickTasks = $quickTasks->map(fn($q) => $attachWorkloadMetadata($q, 'quick_task'))->filter(fn($q) => $q->allocated_hours_today > 0)->values();

        $roundedTotal = round($totalHours, 2);
        $user = User::find($userId);
        $userCapacity = $this->getUserDailyCapacity($user);
        $isWorkingDay = $user ? $user->isWorkingDay($targetDate) : true;

        return [
            'date' => $targetDate,
            'user_id' => $userId,
            'is_working_day' => $isWorkingDay,
            'max_daily_capacity' => $userCapacity,
            'total_hours' => $roundedTotal,
            'remaining_hours' => max(0.0, round($userCapacity - $roundedTotal, 2)),
            'is_overloaded' => ($roundedTotal > ($userCapacity * ((float) app(\App\Services\SettingService::class)->get('overload_threshold_percentage', 100) / 100.0))),
            'overload_hours' => max(0.0, round($roundedTotal - $userCapacity, 2)),
            'plan_posts' => $processedPosts,
            'tasks' => $processedTasks,
            'quick_tasks' => $processedQuickTasks,
        ];
    }

    /**
     * تشفيت عدد محدد من الساعات من مهمة معينة إلى يوم عمل آخر
     */
    public function shiftTaskHours(int $userId, string $taskType, int $taskId, float $hoursToShift, string $fromDate, string $toDate, ?string $reason = null): array
    {
        return DB::transaction(function () use ($userId, $taskType, $taskId, $hoursToShift, $fromDate, $toDate, $reason) {
            User::where('id', $userId)->lockForUpdate()->first();

            $fromDateCarbon = Carbon::parse($fromDate)->toDateString();
            $toDateCarbon = Carbon::parse($toDate)->toDateString();

            $item = $this->findTaskItem($taskType, $taskId);
            if (!$item) {
                throw new \InvalidArgumentException("المهمة المطلوبة غير موجودة");
            }

            // منع تشفيت أي مهمة مكتملة بالفعل مهما كان مصدرها
            if ($this->isItemCompleted($item, $taskType)) {
                throw new \InvalidArgumentException("لا يمكن تشفيت مهمة مكتملة بالفعل.");
            }

            $totalEstimatedHours = $this->getItemEstimatedHours($item, $taskType);

            // حساب الساعات المتاحة حالياً في اليوم المصدر
            $sourceAlloc = TaskWorkloadAllocation::where('allocatable_type', $taskType)
                ->where('allocatable_id', $taskId)
                ->whereDate('work_date', $fromDateCarbon)
                ->first();

            if ($sourceAlloc) {
                $currentSourceHours = (float) $sourceAlloc->allocated_hours;
            } else {
                $otherAllocSum = (float) TaskWorkloadAllocation::where('allocatable_type', $taskType)
                    ->where('allocatable_id', $taskId)
                    ->sum('allocated_hours');
                $currentSourceHours = max(0.0, round($totalEstimatedHours - $otherAllocSum, 2));
            }

            if ($currentSourceHours <= 0) {
                throw new \InvalidArgumentException("لا توجد ساعات عمل مجدولة لهذه المهمة في تاريخ {$fromDateCarbon}");
            }

            if ($hoursToShift > $currentSourceHours) {
                $hoursToShift = $currentSourceHours;
            }

            if ($hoursToShift <= 0) {
                throw new \InvalidArgumentException("عدد الساعات المراد تشفيتها يجب أن يكون أكبر من الصفر");
            }

            $remainingSourceHours = round($currentSourceHours - $hoursToShift, 2);

            // تحديث اليوم المصدر
            if ($sourceAlloc) {
                if ($remainingSourceHours > 0) {
                    $sourceAlloc->allocated_hours = $remainingSourceHours;
                    $sourceAlloc->save();
                } else {
                    $sourceAlloc->delete();
                }
            } else {
                if ($remainingSourceHours > 0) {
                    TaskWorkloadAllocation::create([
                        'user_id' => $userId,
                        'allocatable_type' => $taskType,
                        'allocatable_id' => $taskId,
                        'work_date' => $fromDateCarbon,
                        'allocated_hours' => $remainingSourceHours,
                        'is_shifted' => false,
                        'shifted_from_date' => null,
                        'shift_reason' => null,
                    ]);
                }
            }

            // تحديث اليوم المستهدف
            $targetAlloc = TaskWorkloadAllocation::where('allocatable_type', $taskType)
                ->where('allocatable_id', $taskId)
                ->whereDate('work_date', $toDateCarbon)
                ->first();

            if ($targetAlloc) {
                $targetAlloc->allocated_hours = round($targetAlloc->allocated_hours + $hoursToShift, 2);
                $targetAlloc->is_shifted = true;
                $targetAlloc->shifted_from_date = $fromDateCarbon;
                if ($reason) $targetAlloc->shift_reason = $reason;
                $targetAlloc->save();
            } else {
                TaskWorkloadAllocation::create([
                    'user_id' => $userId,
                    'allocatable_type' => $taskType,
                    'allocatable_id' => $taskId,
                    'work_date' => $toDateCarbon,
                    'allocated_hours' => round($hoursToShift, 2),
                    'is_shifted' => true,
                    'shifted_from_date' => $fromDateCarbon,
                    'shift_reason' => $reason ?: "تم تشفيت {$hoursToShift} ساعة من تاريخ {$fromDateCarbon}",
                ]);
            }

            // تحديث حالة التشفيت في السجل الأصلي
            $item->is_displaced = true;
            if (empty($item->displaced_reason)) {
                $item->displaced_reason = $reason ?: "تم تشفيت {$hoursToShift} ساعة إلى تاريخ {$toDateCarbon}";
            }
            $item->save();

            return [
                'task_type' => $taskType,
                'task_id' => $taskId,
                'shifted_hours' => $hoursToShift,
                'remaining_source_hours' => $remainingSourceHours,
                'from_date' => $fromDateCarbon,
                'to_date' => $toDateCarbon,
                'item_title' => $this->getItemTitle($item, $taskType),
            ];
        });
    }

    /**
     * استرجاع الساعات المشفتة وإعادتها لتاريخها الأصلي
     */
    public function restoreShiftedHours(int $userId, string $taskType, int $taskId, string $targetDate): array
    {
        return DB::transaction(function () use ($userId, $taskType, $taskId, $targetDate) {
            User::where('id', $userId)->lockForUpdate()->first();

            $dateCarbon = Carbon::parse($targetDate)->toDateString();

            $targetAlloc = TaskWorkloadAllocation::where('allocatable_type', $taskType)
                ->where('allocatable_id', $taskId)
                ->whereDate('work_date', $dateCarbon)
                ->where('is_shifted', true)
                ->first();

            if (!$targetAlloc) {
                throw new \InvalidArgumentException("لا توجد ساعات مشفتة لهذه المهمة في هذا التاريخ");
            }

            $hoursToRestore = (float) $targetAlloc->allocated_hours;
            $originDate = $targetAlloc->shifted_from_date;

            if ($originDate) {
                $sourceAlloc = TaskWorkloadAllocation::where('allocatable_type', $taskType)
                    ->where('allocatable_id', $taskId)
                    ->whereDate('work_date', $originDate)
                    ->first();

                if ($sourceAlloc) {
                    $sourceAlloc->allocated_hours = round($sourceAlloc->allocated_hours + $hoursToRestore, 2);
                    $sourceAlloc->save();
                } else {
                    TaskWorkloadAllocation::create([
                        'user_id' => $userId,
                        'allocatable_type' => $taskType,
                        'allocatable_id' => $taskId,
                        'work_date' => $originDate,
                        'allocated_hours' => $hoursToRestore,
                        'is_shifted' => false,
                    ]);
                }
            }

            $targetAlloc->delete();

            // فحص هل ما زال هناك أي سجلات مشفتة أخرى لهذه المهمة
            $hasAnyOtherShift = TaskWorkloadAllocation::where('allocatable_type', $taskType)
                ->where('allocatable_id', $taskId)
                ->where('is_shifted', true)
                ->exists();

            $item = $this->findTaskItem($taskType, $taskId);
            if (!$hasAnyOtherShift && $item) {
                $item->is_displaced = false;
                $item->displaced_reason = null;
                $item->save();
            }

            return [
                'task_type' => $taskType,
                'task_id' => $taskId,
                'restored_hours' => $hoursToRestore,
                'restored_to_date' => $originDate,
                'item_title' => $item ? $this->getItemTitle($item, $taskType) : '',
            ];
        });
    }

    /**
     * تنفيذ الترحيل التلقائي المتتالي القائم على الساعات (Granular Hour-Based Auto Cascade)
     */
    public function autoCascadeWorkload(int $userId, Carbon $startDate, $urgentItem, string $urgentTitle, ?string $urgentType = null): array
    {
        try {
            $cascadeEnabled = (bool) app(\App\Services\SettingService::class)->get('auto_cascade_enabled', true);
        } catch (\Throwable $e) {
            $cascadeEnabled = true;
        }

        if (!$cascadeEnabled) {
            return [
                'cascaded' => false,
                'message' => 'محرك ترحيل المهام التلقائي معطل في الإعدادات العامة.',
                'displaced_items' => []
            ];
        }

        return DB::transaction(function () use ($userId, $startDate, $urgentItem, $urgentTitle, $urgentType) {
            $user = User::where('id', $userId)->lockForUpdate()->first();
            $userCapacity = $this->getUserDailyCapacity($user);

            $displacedItems = [];
            $currentDate = $startDate->copy()->startOfDay();

            if (is_object($urgentItem)) {
                $urgentId = $urgentItem->id;
                $urgentType = ($urgentItem instanceof PlanPost) ? 'plan_post' : (($urgentItem instanceof Task) ? 'task' : 'quick_task');
            } else {
                $urgentId = $urgentItem;
                $urgentType = $urgentType ?: 'plan_post';
            }

            $maxIterations = 14;
            $iteration = 0;
            $safetyWarning = null;

            while ($iteration < $maxIterations) {
                $iteration++;
                $dateString = $currentDate->toDateString();
                $schedule = $this->getEmployeeDaySchedule($userId, $dateString);

                if ($schedule['total_hours'] <= $userCapacity) {
                    break;
                }

                $nextWorkingDay = $this->getNextWorkingDay($currentDate, $user);
                $anyDisplacedForThisDay = false;

                while ($schedule['total_hours'] > $userCapacity) {
                    $excessHours = round($schedule['total_hours'] - $userCapacity, 2);

                    $candidate = $this->selectDisplacementCandidate($schedule, $urgentId, $urgentType, $currentDate, $nextWorkingDay);
                    if (!$candidate) {
                        break;
                    }

                    $candidateItem = $candidate['item'];
                    $candidateType = $candidate['type'];
                    $candidateHoursToday = (float) $candidateItem->allocated_hours_today;

                    if ($candidateHoursToday <= 0) {
                        break;
                    }

                    // اقتطاع وتشفيت الساعات الزائدة بالضبط دون هدر ساعات اليوم
                    $hoursToShift = min($excessHours, $candidateHoursToday);

                    $shiftResult = $this->shiftTaskHours(
                        $userId,
                        $candidateType,
                        $candidateItem->id,
                        $hoursToShift,
                        $dateString,
                        $nextWorkingDay->toDateString(),
                        "تم ترحيل {$hoursToShift} ساعة تلقائياً لإفساح المجال للمهمة الطارئة: {$urgentTitle}"
                    );

                    $displacedItems[] = [
                        'type' => $candidateType,
                        'id' => $candidateItem->id,
                        'title' => $shiftResult['item_title'],
                        'old_date' => $dateString,
                        'new_date' => $nextWorkingDay->toDateString(),
                        'hours' => $hoursToShift,
                    ];
                    $anyDisplacedForThisDay = true;

                    // إعادة فحص جدول اليوم بعد تشفيت هذه الساعات
                    $schedule = $this->getEmployeeDaySchedule($userId, $dateString);
                }

                if ($schedule['total_hours'] > $userCapacity && !$anyDisplacedForThisDay) {
                    $safetyWarning = "⚠️ تنبيه أمان: جدول يوم ({$dateString}) يتجاوز سعة الموظف ({$userCapacity} ساعات عمل)، وتعذر الترحيل لأن كافة المهام المتبقية طارئة أو مقيدة بمواعيد نشر حرجة!";
                    break;
                }

                if (!$anyDisplacedForThisDay) {
                    break;
                }

                // الانتقال لليوم التالي لفحص ما إذا كانت الساعات المنقولة إليه سببت تجاوزاً (تأثير الدومينو)
                $currentDate = $nextWorkingDay;
            }

            return [
                'displaced_items' => $displacedItems,
                'warning' => $safetyWarning,
            ];
        });
    }

    /**
     * تنفيذ الإرجاع الذكي للمهام المرحّلة عند زوال حالة الطوارئ بالساعات
     */
    public function autoRollbackWorkload(int $userId, $urgentItem = null, string $urgentTitle = '', ?string $urgentType = null): array
    {
        return DB::transaction(function () use ($userId, $urgentItem, $urgentTitle, $urgentType) {
            $user = User::where('id', $userId)->lockForUpdate()->first();
            $userCapacity = $this->getUserDailyCapacity($user);

            $rolledBackItems = [];
            $displacedAllocations = TaskWorkloadAllocation::where('user_id', $userId)
                ->where('is_shifted', true)
                ->whereNotNull('shifted_from_date')
                ->orderBy('work_date', 'asc')
                ->get();

            foreach ($displacedAllocations as $alloc) {
                $originDate = $alloc->shifted_from_date;
                if (!$originDate) continue;

                $originSchedule = $this->getEmployeeDaySchedule($userId, $originDate);
                $neededHours = (float) $alloc->allocated_hours;

                // فحص توفر السعة في اليوم الأصلي دون تجاوز سعة الموظف اليومية
                if (($originSchedule['total_hours'] + $neededHours) <= $userCapacity) {
                    $res = $this->restoreShiftedHours($userId, $alloc->allocatable_type, $alloc->allocatable_id, $alloc->work_date);
                    $rolledBackItems[] = [
                        'type' => $alloc->allocatable_type,
                        'id' => $alloc->allocatable_id,
                        'title' => $res['item_title'],
                        'from_date' => $alloc->work_date,
                        'to_date' => $originDate,
                        'hours' => $neededHours,
                    ];
                }
            }

            return [
                'rolled_back_items' => $rolledBackItems,
            ];
        });
    }

    /**
     * اختيار العنصر المرشح للإزاحة من اليوم المزدحم
     */
    protected function selectDisplacementCandidate(array $schedule, $urgentId, string $urgentType, Carbon $currentDate, Carbon $nextWorkingDay): ?array
    {
        $candidates = collect();

        // 1. تصفية مهام المشاريع العامة
        foreach ($schedule['tasks'] as $t) {
            if ($t->is_urgent) continue;
            if ($urgentType === 'task' && (int)$t->id === (int)$urgentId) continue;

            if ($t->project && $t->project->end_date) {
                $projectEnd = Carbon::parse($t->project->end_date)->endOfDay();
                if ($nextWorkingDay->startOfDay()->gt($projectEnd)) {
                    continue;
                }
            }

            $priority = $t->priority ?? 'medium';
            $score = ($priority === 'low') ? 10 : (($priority === 'medium') ? 50 : 80);

            $candidates->push([
                'item' => $t,
                'type' => 'task',
                'score' => $score,
            ]);
        }

        // 2. تصفية المهام السريعة
        foreach ($schedule['quick_tasks'] as $q) {
            if ($q->is_urgent) continue;
            if ($urgentType === 'quick_task' && (int)$q->id === (int)$urgentId) continue;

            $candidates->push([
                'item' => $q,
                'type' => 'quick_task',
                'score' => 20, // أولوية ترحيل عالية للمهام السريعة قبل بوستات النشر الرسمية
            ]);
        }

        // 3. تصفية منشورات الخطط
        foreach ($schedule['plan_posts'] as $p) {
            if ($p->is_urgent) continue;
            if ($urgentType === 'plan_post' && (int)$p->id === (int)$urgentId) continue;

            // قفل أمان موعد النشر
            if ($p->target_date) {
                $publishDate = Carbon::parse($p->target_date)->startOfDay();
                if ($nextWorkingDay->startOfDay()->gte($publishDate)) {
                    continue;
                }
            }

            $score = 40; // بوستات النشر
            $candidates->push([
                'item' => $p,
                'type' => 'plan_post',
                'score' => $score,
            ]);
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        // الأقل في Score يرحل أولاً
        return $candidates->sortBy('score')->first();
    }

    /**
     * استخراج بيانات لوحة اليوم (الشريط الزمني الأفقي الاستعراضي)
     */
    public function getTodayTimelineBoard(int $userId, string $dateString, bool $isManager = false): array
    {
        $targetDate = Carbon::parse($dateString)->toDateString();
        $targetCarbon = Carbon::parse($dateString);

        // أيام وأشهر عربية لضمان العرض السليم
        $arabicDays = ['Sunday' => 'الأحد', 'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'];
        $arabicMonths = [1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'];
        $dayName = $arabicDays[$targetCarbon->format('l')] ?? $targetCarbon->format('l');
        $monthName = $arabicMonths[(int)$targetCarbon->format('n')] ?? $targetCarbon->format('F');
        $dateFormatted = "{$dayName}، {$targetCarbon->format('j')} {$monthName} {$targetCarbon->format('Y')}";

        $user = User::find($userId);

        // قائمة الموظفين للمدير للتنقل والفلترة السريعة
        $employeesList = [];
        if ($isManager) {
            $employees = User::where('role', 'employee')->select('id', 'name', 'job_title', 'role')->get();
            foreach ($employees as $emp) {
                $employeesList[] = [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'job_title' => $emp->job_title ?? 'عضو فريق',
                    'role' => is_object($emp->role) ? $emp->role->value : $emp->role,
                    'is_selected' => ($emp->id === $userId),
                ];
            }
        }

        // 1. جلب التخصيصات اليومية
        $dayAllocations = TaskWorkloadAllocation::where('user_id', $userId)
            ->whereDate('work_date', $targetDate)
            ->get()
            ->keyBy(fn($a) => "{$a->allocatable_type}_{$a->allocatable_id}");

        // 2. جلب بوستات الخطط
        $planPosts = PlanPost::with(['plan.client'])
            ->where('designer_id', $userId)
            ->where(function ($q) use ($targetDate) {
                $q->whereDate('deadline', $targetDate)
                  ->orWhere(function ($sub) use ($targetDate) {
                      $sub->whereNull('deadline')->whereDate('target_date', $targetDate);
                  });
            })
            ->get();

        // 3. جلب مهام المشاريع
        $tasks = Task::with('project')
            ->where('assigned_to', $userId)
            ->whereDate('due_date', $targetDate)
            ->get();

        // 4. جلب المهام السريعة
        $quickTasks = QuickTask::with(['creator', 'assignee'])
            ->where('assigned_to', $userId)
            ->whereDate('deadline', $targetDate)
            ->get();

        // 5. دمج المهام التي لديها تخصيص صريح مسجل لليوم
        foreach ($dayAllocations as $alloc) {
            $type = $alloc->allocatable_type;
            $id = (int) $alloc->allocatable_id;
            if ($type === 'plan_post' && !$planPosts->contains('id', $id)) {
                $p = PlanPost::with(['plan.client'])->find($id);
                if ($p) $planPosts->push($p);
            } elseif ($type === 'task' && !$tasks->contains('id', $id)) {
                $t = Task::with('project')->find($id);
                if ($t) $tasks->push($t);
            } elseif ($type === 'quick_task' && !$quickTasks->contains('id', $id)) {
                $q = QuickTask::with(['creator', 'assignee'])->find($id);
                if ($q) $quickTasks->push($q);
            }
        }

        $rawItems = collect();
        $shiftsAlerts = [];
        $totalHours = 0.0;
        $completedHours = 0.0;
        $shiftedInHours = 0.0;
        $shiftedOutHours = 0.0;
        $shiftedTasksCount = 0;

        $collector = function ($item, string $type) use (
            $targetDate, $dayAllocations, &$rawItems, &$shiftsAlerts,
            &$totalHours, &$completedHours, &$shiftedInHours, &$shiftedOutHours, &$shiftedTasksCount
        ) {
            $totalEst = $this->getItemEstimatedHours($item, $type);
            $allocKey = "{$type}_{$item->id}";
            $alloc = $dayAllocations->get($allocKey);

            if ($alloc) {
                $todayHours = (float) $alloc->allocated_hours;
                $isShifted = (bool) $alloc->is_shifted;
                $shiftedFrom = $alloc->shifted_from_date ? Carbon::parse($alloc->shifted_from_date)->toDateString() : null;
                $shiftReason = $alloc->shift_reason;
            } else {
                $otherAllocSum = (float) TaskWorkloadAllocation::where('allocatable_type', $type)
                    ->where('allocatable_id', $item->id)
                    ->whereDate('work_date', '!=', $targetDate)
                    ->sum('allocated_hours');
                $todayHours = max(0.0, round($totalEst - $otherAllocSum, 2));
                $isShifted = (bool) $item->is_displaced;
                $shiftedFrom = null;
                $shiftReason = $item->displaced_reason;
            }

            $shiftedToFuture = (float) TaskWorkloadAllocation::where('allocatable_type', $type)
                ->where('allocatable_id', $item->id)
                ->whereDate('work_date', '>', $targetDate)
                ->sum('allocated_hours');

            // إذا لم يكن لها ساعات اليوم ولم يكن هناك تشفيت للغد، نتخطاها
            if ($todayHours <= 0 && $shiftedToFuture <= 0) {
                return;
            }

            $isPartiallyShifted = ($shiftedToFuture > 0);
            $isShiftedToToday = ($shiftedFrom !== null);

            $itemTitle = $this->getItemTitle($item, $type);

            // تتبع وتسجيل تنبيهات التشفيت لليوم
            if ($isShiftedToToday) {
                $shiftedInHours += $todayHours;
                $shiftedTasksCount++;
                $shiftsAlerts[] = [
                    'id' => "shift_in_{$type}_{$item->id}",
                    'type' => 'shifted_in',
                    'task_type' => $type,
                    'task_id' => $item->id,
                    'title' => $itemTitle,
                    'hours' => $todayHours,
                    'from_date' => $shiftedFrom,
                    'reason' => $shiftReason ?: 'تم ترحيل ساعات المهمة إلى جدول أعمال اليوم',
                ];
            }

            if ($isPartiallyShifted) {
                $shiftedOutHours += $shiftedToFuture;
                $shiftedTasksCount++;
                $shiftsAlerts[] = [
                    'id' => "shift_out_{$type}_{$item->id}",
                    'type' => 'shifted_out',
                    'task_type' => $type,
                    'task_id' => $item->id,
                    'title' => $itemTitle,
                    'hours' => $shiftedToFuture,
                    'reason' => $shiftReason ?: 'تم تشفيت جزء من ساعات المهمة إلى الغد',
                ];
            }

            // تحديد الحالة ونسبة الإنجاز
            $status = $item->status ?? 'pending';
            $isCompleted = $this->isItemCompleted($item, $type);
            if ($type === 'plan_post' && ($item->manager_review_status === 'مرفوض' || $item->review_status === 'مرفوض')) {
                $status = 'rejected';
            }

            if ($isCompleted) {
                $completedHours += $todayHours;
            }
            $totalHours += $todayHours;

            $subtitle = '';
            $clientName = '';
            if ($type === 'plan_post') {
                $clientName = $item->plan?->client?->name ?? '';
                $subtitle = $item->plan?->title ?? ($clientName ? "خطة: {$clientName}" : '');
            } elseif ($type === 'task') {
                $clientName = '';
                $subtitle = $item->project?->name ? "مشروع: {$item->project->name}" : '';
            } elseif ($type === 'quick_task') {
                $subtitle = $item->creator ? "بواسطة: {$item->creator->name}" : 'توجيه سريع';
            }

            $rawItems->push([
                'id' => $item->id,
                'unique_key' => "{$type}_{$item->id}",
                'task_type' => $type,
                'type_label' => $type === 'plan_post' ? 'منشور خطة' : ($type === 'task' ? 'مهمة مشروع' : 'مهمة سريعة'),
                'title' => $item->title ?? $itemTitle,
                'subtitle' => $subtitle,
                'client_name' => $clientName,
                'status' => $status,
                'is_completed' => $isCompleted,
                'allocated_hours_today' => $todayHours,
                'total_estimated_hours' => $totalEst,
                'shifted_hours_to_future' => $shiftedToFuture,
                'is_partially_shifted' => $isPartiallyShifted,
                'is_shifted_to_today' => $isShiftedToToday,
                'shifted_from_date' => $shiftedFrom,
                'shift_reason' => $shiftReason,
                'is_urgent' => (bool) ($item->is_urgent ?? false),
                'voice_record_url' => $item->voice_record_url ?? null,
                'attachment_url' => $item->attachment_url ?? null,
                'description' => $item->description ?? $item->content_text ?? null,
                'post_type' => $item->post_type ?? null,
                'deadline' => $item->deadline ?? $item->due_date ?? null,
                'content_plan_id' => $type === 'plan_post' ? ($item->content_plan_id ?? $item->plan_id ?? $item->plan?->id) : null,
                'project_id' => $type === 'task' ? ($item->project_id ?? $item->project?->id) : null,
            ]);
        };

        foreach ($planPosts as $p) $collector($p, 'plan_post');
        foreach ($tasks as $t) $collector($t, 'task');
        foreach ($quickTasks as $q) $collector($q, 'quick_task');

        // ترتيب المهام لتشكيل شريط زمني منطقي:
        // العاجلة والجارية أولاً، ثم قيد الانتظار، ثم المكتملة
        $sortedItems = $rawItems->sort(function ($a, $b) {
            if ($a['is_completed'] !== $b['is_completed']) {
                return $a['is_completed'] ? 1 : -1;
            }
            if ($a['is_urgent'] !== $b['is_urgent']) {
                return $a['is_urgent'] ? -1 : 1;
            }
            if ($a['status'] === 'in_progress' && $b['status'] !== 'in_progress') {
                return -1;
            }
            if ($b['status'] === 'in_progress' && $a['status'] !== 'in_progress') {
                return 1;
            }
            return 0;
        })->values();

        // حساب التقسيم الزمني التقديري لساعات اليوم (Roadmap Timeline) بناءً على وقت بدء دوام الموظف
        $startTimeStr = ($user && $user->work_start_time) ? substr($user->work_start_time, 0, 5) : '09:00';
        $startWork = Carbon::parse($targetDate . ' ' . $startTimeStr . ':00');
        $runningClock = $startWork->copy();
        $now = Carbon::now();

        $timelineItems = [];
        foreach ($sortedItems as $index => $itemData) {
            $durationMins = max(30, (int) round($itemData['allocated_hours_today'] * 60));
            $slotStart = $runningClock->copy();
            $slotEnd = $runningClock->copy()->addMinutes($durationMins);
            $runningClock->addMinutes($durationMins);

            $isCurrent = ($now->between($slotStart, $slotEnd) && !$itemData['is_completed']);

            $formatArabicTime = function (Carbon $c) {
                $hour = $c->format('g');
                $min = $c->format('i');
                $period = $c->format('A') === 'AM' ? 'ص' : 'م';
                return "{$hour}:{$min} {$period}";
            };

            $itemData['order_index'] = $index + 1;
            $itemData['start_time'] = $slotStart->format('H:i');
            $itemData['end_time'] = $slotEnd->format('H:i');
            $itemData['start_time_label'] = $formatArabicTime($slotStart);
            $itemData['end_time_label'] = $formatArabicTime($slotEnd);
            $itemData['time_slot_label'] = "{$formatArabicTime($slotStart)} - {$formatArabicTime($slotEnd)}";
            $itemData['duration_label'] = "{$itemData['allocated_hours_today']} س";
            $itemData['is_current_slot'] = $isCurrent;

            $timelineItems[] = $itemData;
        }

        $roundedTotal = round($totalHours, 2);
        $roundedCompleted = round($completedHours, 2);
        $remainingHours = max(0.0, round($roundedTotal - $roundedCompleted, 2));

        $userCapacity = $this->getUserDailyCapacity($user);
        $nextWorkingDay = $this->getNextWorkingDay($targetCarbon, $user)->toDateString();
        $isWorkingDayToday = $user ? $user->isWorkingDay($targetDate) : true;

        return [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'job_title' => $user->job_title ?? 'عضو فريق',
                'role' => is_object($user->role) ? $user->role->value : $user->role,
                'phone' => $user->phone ?? null,
                'work_start_day' => $user->work_start_day ?? 'saturday',
                'work_end_day' => $user->work_end_day ?? 'thursday',
                'working_days' => $user->working_days ?? ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
                'work_start_time' => $user->work_start_time ? substr($user->work_start_time, 0, 5) : '09:00',
                'work_end_time' => $user->work_end_time ? substr($user->work_end_time, 0, 5) : '17:00',
                'daily_work_hours' => $userCapacity,
            ] : null,
            'employees_list' => $employeesList,
            'is_manager' => $isManager,
            'date' => $targetDate,
            'date_formatted' => $dateFormatted,
            'stats' => [
                'total_hours_today' => $roundedTotal,
                'completed_hours' => $roundedCompleted,
                'remaining_hours' => $remainingHours,
                'max_daily_capacity' => $userCapacity,
                'capacity_percentage' => (int) round(($roundedTotal / $userCapacity) * 100),
                'overload_hours' => max(0.0, round($roundedTotal - $userCapacity, 2)),
                'is_overloaded' => ($roundedTotal > ($userCapacity * ((float) app(\App\Services\SettingService::class)->get('overload_threshold_percentage', 100) / 100.0))),
                'is_working_day_today' => $isWorkingDayToday,
                'next_working_day' => $nextWorkingDay,
                'total_tasks_count' => count($timelineItems),
                'completed_tasks_count' => count(array_filter($timelineItems, fn($t) => $t['is_completed'])),
                'in_progress_tasks_count' => count(array_filter($timelineItems, fn($t) => $t['status'] === 'in_progress')),
                'pending_tasks_count' => count(array_filter($timelineItems, fn($t) => in_array($t['status'], ['pending', 'not_started']))),
                'shifted_tasks_count' => $shiftedTasksCount,
                'shifted_in_hours' => round($shiftedInHours, 2),
                'shifted_out_hours' => round($shiftedOutHours, 2),
            ],
            'shifts_alerts' => $shiftsAlerts,
            'timeline_items' => $timelineItems,
        ];
    }
}

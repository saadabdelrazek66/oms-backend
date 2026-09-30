<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ContentPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDuplicatePlans extends Command
{
    protected $signature = 'plans:auto-duplicate';
    protected $description = 'استنساخ الخطط بناءً على مواعيد التسليم الفعيلة للشهر القادم';

    public function handle()
    {
        $this->info('بدأ فحص الخطط للتكرار التلقائي...');

        // نجلب كل الخطط المفعل تكرارها
        $plans = ContentPlan::where('is_recurring', true)->get();

        if ($plans->isEmpty()) {
            $this->info('لا توجد خطط مفعل عليها التكرار التلقائي.');
            return;
        }

        $count = 0;
        $daysBeforeWorkStarts = 7; // عدد الأيام اللي حابب الخطة تنزل فيها قبل موعد التسليم الجديد

        foreach ($plans as $plan) {
            // 1. نحدد التاريخ المرجعي الذي يبدأ عنده "الشغل" (الأولوية للتسليم الابتدائي ثم النهائي)
            $referenceDate = $plan->planned_initial_delivery_date ?? $plan->planned_delivery_date;

            if (!$referenceDate) {
                continue; // تخطي إذا لم تكن هناك تواريخ تسليم محددة
            }

            // 2. نحسب موعد هذا "الشغل" في الشهر القادم
            $nextMonthWorkDate = Carbon::parse($referenceDate)->addMonthNoOverflow();

            // 3. نحسب الموعد الذي يجب أن ينشئ فيه النظام الخطة (قبل موعد الشغل بـ 7 أيام)
            $triggerDate = $nextMonthWorkDate->copy()->subDays($daysBeforeWorkStarts);

            // 4. هل اليوم تخطى أو ساوى الموعد المطلوب لإنشاء الخطة؟
            if (now()->greaterThanOrEqualTo($triggerDate)) {
                try {
                    DB::transaction(function () use ($plan, &$count) {
                        $newPlan = $plan->replicate();

                        // حساب وتحديث جميع التواريخ للشهر الجديد
                        $newPlan->start_date = Carbon::parse($plan->start_date)->addMonthNoOverflow();
                        $newPlan->end_date = Carbon::parse($plan->end_date)->addMonthNoOverflow();
                        $newPlan->planned_delivery_date = Carbon::parse($plan->planned_delivery_date)->addMonthNoOverflow();

                        if ($plan->planned_review_date) {
                            $newPlan->planned_review_date = Carbon::parse($plan->planned_review_date)->addMonthNoOverflow();
                        }
                        if ($plan->planned_initial_delivery_date) {
                            $newPlan->planned_initial_delivery_date = Carbon::parse($plan->planned_initial_delivery_date)->addMonthNoOverflow();
                        }

                        // تصفير تواريخ العمل الفعلي لأنها خطة جديدة بالكامل
                        $newPlan->actual_initial_delivery_date = null;
                        $newPlan->actual_delivery_date = null;
                        $newPlan->actual_review_date = null;
                        $newPlan->status = 'pending';

                        // تمرير راية التكرار للخطة الجديدة
                        $newPlan->is_recurring = true;
                        $newPlan->save();

                        // نقل فريق العمل
                        $plan->load('users');
                        foreach ($plan->users as $teamMember) {
                            $newPlan->users()->attach($teamMember->id, [
                                'task_role' => $teamMember->pivot->task_role
                            ]);
                        }

                        // إيقاف التكرار في الخطة القديمة حتى لا تتكرر مرة أخرى
                        $plan->update([
                            'is_recurring' => false,
                            'last_recurrence_handled_at' => now()
                        ]);

                        $count++;
                    });
                } catch (\Exception $e) {
                    Log::error("خطأ في استنساخ الخطة ID {$plan->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("تم استنساخ {$count} خطة بنجاح استناداً لمواعيد التسليم!");
    }
}

<?php

namespace App\Services;

use App\Models\ContentPlan;
use Illuminate\Support\Facades\DB;

class ContentPlanService
{
    public function createPlan(array $data)
    {
        return DB::transaction(function () use ($data) {
            $plan = ContentPlan::create($data);
            $this->syncUsers($plan, $data);
            return $plan;
        });
    }

    public function updatePlan(ContentPlan $plan, array $data)
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update($data);
            $this->syncUsers($plan, $data);
            return $plan;
        });
    }

    private function syncUsers(ContentPlan $plan, array $data)
    {
        // 1. مسح كل الموظفين المربوطين بالخطة لتحديثهم من جديد
        $plan->users()->detach();

        // 2. إضافة المسئولين
        if (!empty($data['responsible_ids'])) {
            foreach ($data['responsible_ids'] as $id) {
                $plan->users()->attach($id, ['task_role' => 'responsible']);
            }
        }

        // 3. إضافة المراجعين (فقط إذا كانت الخطة تتطلب مراجعة)
        if (!empty($data['requires_review']) && !empty($data['reviewer_ids'])) {
            foreach ($data['reviewer_ids'] as $id) {
                $plan->users()->attach($id, ['task_role' => 'reviewer']);
            }
        }

        // 4. إضافة المنفذين
        if (!empty($data['executor_ids'])) {
            foreach ($data['executor_ids'] as $id) {
                $plan->users()->attach($id, ['task_role' => 'executor']);
            }
        }
    }

    // إرسال الخطة للمراجعة الداخلية (من المسئول إلى المراجع)
    public function submitForReview(ContentPlan $plan)
    {
        $plan->status = 'under_review';
        $plan->save();
        return $plan;
    }

    // التسليم النهائي للعميل (من المسئول)
    public function submitFinalDelivery(ContentPlan $plan)
    {
        $plan->actual_delivery_date = now();
        $plan->status = 'completed';
        $plan->save();
        return $plan;
    }

    // قبول الخطة من قبل المراجع (تحويلها لجاهزة للتسليم)
    public function approvePlan(ContentPlan $plan, $reviewerId)
    {
        $plan->actual_review_date = now();
        $plan->status = 'reviewed'; // تمت المراجعة وجاهزة للتسليم
        $plan->save();

        $plan->reviewHistories()->create([
            'reviewer_id' => $reviewerId,
            'action' => 'approved',
            'notes' => 'تمت الموافقة من المراجعة الداخلية، الخطة جاهزة للتسليم النهائي.',
        ]);

        return $plan;
    }

    // رفض الخطة من قبل المراجع (إعادتها للمسئول)
    public function rejectPlan(ContentPlan $plan, $reviewerId, $notes)
    {
        $plan->status = 'rejected';
        $plan->save();

        $plan->reviewHistories()->create([
            'reviewer_id' => $reviewerId,
            'action' => 'rejected',
            'notes' => $notes,
        ]);

        return $plan;
    }
}

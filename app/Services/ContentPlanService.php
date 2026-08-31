<?php

namespace App\Services;

use App\Models\ContentPlan;
use Illuminate\Support\Facades\DB;

class ContentPlanService
{
    // إنشاء خطة جديدة وربط الموظفين بأدوارهم
    public function createPlan(array $data)
    {
        return DB::transaction(function () use ($data) {
            $plan = ContentPlan::create($data);
            $this->syncPlanUsers($plan, $data);
            return $plan->load('users');
        });
    }

    // تعديل الخطة وتحديث الموظفين
    public function updatePlan(ContentPlan $plan, array $data)
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update($data);
            $this->syncPlanUsers($plan, $data);
            return $plan->load('users');
        });
    }

    // دالة خاصة لربط الموظفين (تدعم تكرار نفس الموظف بأدوار مختلفة)
    private function syncPlanUsers(ContentPlan $plan, array $data)
    {
        // إزالة جميع الارتباطات القديمة لهذه الخطة
        $plan->users()->detach();

        // إضافة قائمة المسئولين
        if (!empty($data['responsible_ids'])) {
            foreach ($data['responsible_ids'] as $id) {
                $plan->users()->attach($id, ['task_role' => 'responsible']);
            }
        }

        // إضافة قائمة المختصين
        if (!empty($data['specialist_ids'])) {
            foreach ($data['specialist_ids'] as $id) {
                $plan->users()->attach($id, ['task_role' => 'specialist']);
            }
        }

        // إضافة قائمة القائمين بالخطة
        if (!empty($data['executor_ids'])) {
            foreach ($data['executor_ids'] as $id) {
                $plan->users()->attach($id, ['task_role' => 'executor']);
            }
        }
    }

    // تسجيل وقت إنهاء المراجعة (للموظف)
    public function markReviewComplete(ContentPlan $plan)
    {
        $plan->update(['actual_review_date' => now()]);
        return $plan;
    }

    // تسجيل وقت التسليم الفعلي (للموظف)
    public function markFinalDelivery(ContentPlan $plan)
    {
        $plan->update(['actual_delivery_date' => now()]);
        return $plan;
    }
}

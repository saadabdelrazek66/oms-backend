<?php

namespace App\Services;

use App\Models\ContentPlan;
use Illuminate\Support\Facades\DB;
use App\Rules\ValidDriveFileLink;
use App\Enums\DriveLinkType;
use Illuminate\Validation\ValidationException;
class ContentPlanService
{
    public function createPlan(array $data)
    {
        return DB::transaction(function () use ($data) {
            $plan = ContentPlan::create($data);
            $this->syncUsers($plan, $data);
            $this->syncItems($plan, $data);
            return $plan;
        });
    }

    public function updatePlan(ContentPlan $plan, array $data)
    {
        return DB::transaction(function () use ($plan, $data) {
            $plan->update($data);
            $this->syncUsers($plan, $data);
            $this->syncItems($plan, $data);
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

public function submitForReview(ContentPlan $plan, $userId, string $link, ?string $notes = null)
    {
        $plan->status = 'under_review';
        $plan->final_link = $link;
        if (!is_null($notes)) {
            $plan->notes = $notes;
        }
        
        if (is_null($plan->actual_initial_delivery_date)) {
            $plan->actual_initial_delivery_date = now();
        }
        
        $plan->save();

        $plan->reviewHistories()->create([
            'reviewer_id' => $userId,
            'action' => 'submitted',
            'notes' => !empty($notes) ? $notes : 'تم تسليم الخطة للمراجعة الداخلية.',
        ]);

        return $plan;
    }

    public function submitFinalDelivery(ContentPlan $plan, string $link, ?string $notes = null)
    {
        $plan->actual_delivery_date = now();
        $plan->status = 'completed';
        $plan->final_link = $link;
        if (!is_null($notes)) {
            $plan->notes = $notes;
        }
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

    private function syncItems(ContentPlan $plan, array $data)
    {
        if (!isset($data['items']) || !is_array($data['items'])) {
            return;
        }

        $plan->items()->delete();

        $totalPlanHours = 0;

        foreach ($data['items'] as $item) {
            $name = trim($item['item_name'] ?? $item['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $hoursPerUnit = max(0, (float) ($item['hours_per_unit'] ?? $item['estimated_hours'] ?? 0));
            $totalHours = (float) ($item['total_hours'] ?? ($quantity * $hoursPerUnit));

            $plan->items()->create([
                'plan_item_estimate_id' => $item['plan_item_estimate_id'] ?? $item['id'] ?? null,
                'item_name' => $name,
                'quantity' => $quantity,
                'hours_per_unit' => $hoursPerUnit,
                'total_hours' => $totalHours,
                'unit' => $item['unit'] ?? 'hour',
            ]);

            $totalPlanHours += $totalHours;
        }

        $plan->update(['total_estimated_hours' => $totalPlanHours]);
    }
}

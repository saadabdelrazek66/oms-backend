<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\ContentPlan;
use App\Models\PlanPost;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WorkloadDemoTestSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 8;
        $user = User::find($userId);

        if (!$user) {
            $this->command->error("الموظف برقم ID = {$userId} غير موجود!");
            return;
        }

        $this->command->info("تنظيف الخطط والمنشورات التجريبية السابقة...");
        
        // مسح البوستات والخطط التجريبية السابقة 21, 22, 23 وأي خطة تجريبية
        $oldPlanIds = ContentPlan::whereIn('id', [21, 22, 23])
            ->orWhere('name', 'like', '%تجربة%')
            ->pluck('id');

        if ($oldPlanIds->isNotEmpty()) {
            PlanPost::whereIn('content_plan_id', $oldPlanIds)->delete();
            DB::table('content_plan_user')->whereIn('content_plan_id', $oldPlanIds)->delete();
            ContentPlan::whereIn('id', $oldPlanIds)->forceDelete();
        }

        // مسح مهام المشاريع التجريبية السابقة للموظف 8
        Task::where('assigned_to', $userId)->where('title', 'like', '%تجربة%')->delete();
        Task::where('assigned_to', $userId)->where('title', 'like', '%تصميم هوية بصرية وبنرات%')->delete();

        // 1. عميل مخصص وواضح الاسم
        $client = Client::firstOrCreate(
            ['name' => '⭐ شركة اختبار الترحيل الذكي'],
            ['phones' => '[]', 'emails' => '[]']
        );

        // 2. مشروع تجريبي
        $project = Project::firstOrCreate(
            ['name' => 'مشروع تجربة نظام الطوارئ والترحيل (الموظف 8)'],
            [
                'description' => 'مشروع لفحص سعة العمل اليومية 8 ساعات وخوارزمية الترحيل المتتالي عند إضافة مهام طارئة.',
                'status' => 'جاري التنفيذ',
                'start_date' => Carbon::today()->startOfMonth()->toDateString(),
                'end_date' => Carbon::today()->addMonth()->endOfMonth()->toDateString(),
            ]
        );
        $project->users()->syncWithoutDetaching([$user->id]);

        // 3. مهمة مشروع لليوم (ساعتان)
        $today = Carbon::today();
        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'تصميم هوية بصرية وبنرات للمشروع (2 ساعة)',
            'created_by' => 1,
            'assigned_to' => $user->id,
            'description' => 'مهمة تصميمية أساسية مجدولة لليوم بسعة ساعتين عمل.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => $today->copy()->setTime(17, 0, 0),
            'estimated_hours' => 2.0,
            'is_urgent' => false,
            'is_displaced' => false,
        ]);

        // 4. خطة محتوى معتمدة كلياً ومفتوحة
        DB::table('content_plans')->where('id', 22)->delete();
        $plan = ContentPlan::create([
            'id' => 22,
            'client_id' => $client->id,
            'plan_type' => 'سوشيال ميديا',
            'status' => 'completed',
            'requires_review' => false,
            'client_approved_at' => now(),
            'start_date' => $today->copy()->startOfWeek()->toDateString(),
            'end_date' => $today->copy()->addWeeks(2)->toDateString(),
            'planned_delivery_date' => $today->copy()->addWeeks(2)->toDateString(),
            'notes' => 'خطة اختبار سعة العمل والتشفيت التلقائي.',
        ]);

        DB::table('content_plan_user')->insert([
            'content_plan_id' => $plan->id,
            'user_id' => $user->id,
            'task_role' => 'responsible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. إضافة المنشورات الأربعة
        $tomorrow = Carbon::tomorrow();

        // بوست 1 (اليوم الأربعاء - 3 ساعات)
        $post1 = PlanPost::create([
            'content_plan_id' => $plan->id,
            'designer_id' => $user->id,
            'post_type' => 'Carousels',
            'estimated_hours' => 3.0,
            'target_date' => $today->toDateString(),
            'deadline' => $today->copy()->setTime(14, 0, 0),
            'objective' => 'توعوي',
            'caption' => 'منشور كاروسيل مجدول لليوم (3 ساعات عمل)',
            'publishing_platform' => json_encode(['Instagram', 'Facebook']),
            'review_status' => 'قيد الانتظار',
            'manager_review_status' => 'قيد الانتظار',
            'is_urgent' => false,
            'is_displaced' => false,
        ]);

        // بوست 2 (اليوم الأربعاء - 2 ساعة)
        $post2 = PlanPost::create([
            'content_plan_id' => $plan->id,
            'designer_id' => $user->id,
            'post_type' => 'Static Posts',
            'estimated_hours' => 2.0,
            'target_date' => $today->toDateString(),
            'deadline' => $today->copy()->setTime(17, 0, 0),
            'objective' => 'تفاعلي',
            'caption' => 'منشور ثابت مجدول لليوم (ساعتان عمل)',
            'publishing_platform' => json_encode(['Instagram']),
            'review_status' => 'قيد الانتظار',
            'manager_review_status' => 'قيد الانتظار',
            'is_urgent' => false,
            'is_displaced' => false,
        ]);

        // بوست 3 (غداً الخميس - 4 ساعات)
        $post3 = PlanPost::create([
            'content_plan_id' => $plan->id,
            'designer_id' => $user->id,
            'post_type' => '(Reels / TikToks / Shorts): متوسط الجودة',
            'estimated_hours' => 4.0,
            'target_date' => $tomorrow->toDateString(),
            'deadline' => $tomorrow->copy()->setTime(14, 0, 0),
            'objective' => 'فيديو',
            'caption' => 'فيديو ريلز مجدول ليوم الخميس (4 ساعات عمل)',
            'publishing_platform' => json_encode(['Instagram', 'TikTok']),
            'review_status' => 'قيد الانتظار',
            'manager_review_status' => 'قيد الانتظار',
            'is_urgent' => false,
            'is_displaced' => false,
        ]);

        // بوست 4 (غداً الخميس - 3 ساعات)
        $post4 = PlanPost::create([
            'content_plan_id' => $plan->id,
            'designer_id' => $user->id,
            'post_type' => 'Multi-image',
            'estimated_hours' => 3.0,
            'target_date' => $tomorrow->toDateString(),
            'deadline' => $tomorrow->copy()->setTime(17, 0, 0),
            'objective' => 'معرض صور',
            'caption' => 'منشور متعدد الصور مجدول ليوم الخميس (3 ساعات عمل)',
            'publishing_platform' => json_encode(['Facebook']),
            'review_status' => 'قيد الانتظار',
            'manager_review_status' => 'قيد الانتظار',
            'is_urgent' => false,
            'is_displaced' => false,
        ]);

        $this->command->info("==================================================");
        $this->command->info("تم تجهيز الخطة بنجاح برقم ID: [{$plan->id}]");
        $this->command->info("اسم الخطة: {$plan->name}");
        $this->command->info("رابط الشيت المباشر: /content-plans/{$plan->id}/spreadsheet");
        $this->command->info("جدول اليوم (الأربعاء): 7 ساعات (مهمة 2 س + بوست 3 س + بوست 2 س)");
        $this->command->info("جدول غداً (الخميس): 7 ساعات (بوست 4 س + بوست 3 س)");
        $this->command->info("==================================================");
    }
}

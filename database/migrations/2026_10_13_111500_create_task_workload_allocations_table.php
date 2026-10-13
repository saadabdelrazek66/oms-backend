<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إضافة حقول سعة العمل والتشفيت للمهام السريعة (quick_tasks)
        Schema::table('quick_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('quick_tasks', 'estimated_hours')) {
                $table->decimal('estimated_hours', 4, 2)->default(1.00)->after('description');
            }
            if (!Schema::hasColumn('quick_tasks', 'is_urgent')) {
                $table->boolean('is_urgent')->default(false)->after('estimated_hours');
            }
            if (!Schema::hasColumn('quick_tasks', 'is_displaced')) {
                $table->boolean('is_displaced')->default(false)->after('is_urgent');
            }
            if (!Schema::hasColumn('quick_tasks', 'original_deadline')) {
                $table->dateTime('original_deadline')->nullable()->after('is_displaced');
            }
            if (!Schema::hasColumn('quick_tasks', 'displaced_reason')) {
                $table->string('displaced_reason')->nullable()->after('original_deadline');
            }
        });

        // 2. إنشاء جدول التوزيع اليومي لساعات المهام والتشفيت بالساعات
        if (!Schema::hasTable('task_workload_allocations')) {
            Schema::create('task_workload_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('allocatable_type'); // plan_post, task, quick_task
                $table->unsignedBigInteger('allocatable_id');
                $table->date('work_date');
                $table->decimal('allocated_hours', 4, 2);
                $table->boolean('is_shifted')->default(false);
                $table->date('shifted_from_date')->nullable();
                $table->string('shift_reason')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'work_date']);
                $table->index(['allocatable_type', 'allocatable_id', 'work_date'], 'workload_alloc_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_workload_allocations');

        Schema::table('quick_tasks', function (Blueprint $table) {
            $cols = array_filter(
                ['estimated_hours', 'is_urgent', 'is_displaced', 'original_deadline', 'displaced_reason'],
                fn($c) => Schema::hasColumn('quick_tasks', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

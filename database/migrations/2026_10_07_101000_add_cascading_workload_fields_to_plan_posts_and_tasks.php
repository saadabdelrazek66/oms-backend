<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_posts', 'is_urgent')) {
                $table->boolean('is_urgent')->default(false)->after('target_date');
            }
            if (!Schema::hasColumn('plan_posts', 'is_displaced')) {
                $table->boolean('is_displaced')->default(false)->after('is_urgent');
            }
            if (!Schema::hasColumn('plan_posts', 'original_deadline')) {
                $table->dateTime('original_deadline')->nullable()->after('is_displaced');
            }
            if (!Schema::hasColumn('plan_posts', 'displaced_reason')) {
                $table->string('displaced_reason')->nullable()->after('original_deadline');
            }
            if (!Schema::hasColumn('plan_posts', 'estimated_hours')) {
                $table->decimal('estimated_hours', 4, 2)->nullable()->after('displaced_reason');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'is_urgent')) {
                $table->boolean('is_urgent')->default(false)->after('priority');
            }
            if (!Schema::hasColumn('tasks', 'is_displaced')) {
                $table->boolean('is_displaced')->default(false)->after('is_urgent');
            }
            if (!Schema::hasColumn('tasks', 'original_due_date')) {
                $table->dateTime('original_due_date')->nullable()->after('is_displaced');
            }
            if (!Schema::hasColumn('tasks', 'displaced_reason')) {
                $table->string('displaced_reason')->nullable()->after('original_due_date');
            }
            if (!Schema::hasColumn('tasks', 'estimated_hours')) {
                $table->decimal('estimated_hours', 4, 2)->default(2.00)->after('displaced_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $cols = array_filter(['is_displaced', 'original_deadline', 'displaced_reason', 'estimated_hours'], fn($c) => Schema::hasColumn('plan_posts', $c));
            if (!empty($cols)) $table->dropColumn($cols);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $cols = array_filter(['is_urgent', 'is_displaced', 'original_due_date', 'displaced_reason', 'estimated_hours'], fn($c) => Schema::hasColumn('tasks', $c));
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};

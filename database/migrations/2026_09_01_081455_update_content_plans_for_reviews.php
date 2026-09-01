<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            // إضافة حقل لتحديد هل الخطة تحتاج لمراجعة
            $table->boolean('requires_review')->default(false)->after('plan_type');

            // إضافة حقل لحالة الخطة (pending, under_review, rejected, completed)
            $table->string('status')->default('pending')->after('requires_review');

            // جعل تاريخ المراجعة اختيارياً (لأنه قد لا توجد مراجعة)
            $table->dateTime('planned_review_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn(['requires_review', 'status']);
            $table->dateTime('planned_review_date')->nullable(false)->change();
        });
    }
};

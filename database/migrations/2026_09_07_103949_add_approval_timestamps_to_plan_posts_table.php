<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            // إضافة حقل وقت اعتماد القسم (إجماع المراجعين)
            $table->dateTime('department_approved_at')->nullable()->after('review_status');

            // إضافة حقل وقت الاعتماد النهائي للمدير
            $table->dateTime('manager_approved_at')->nullable()->after('manager_review_status');
        });
    }

    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn(['department_approved_at', 'manager_approved_at']);
        });
    }
};

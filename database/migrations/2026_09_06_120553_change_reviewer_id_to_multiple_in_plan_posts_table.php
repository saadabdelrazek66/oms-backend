<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            // حذف العلاقة والعمود القديم (الذي يقبل مراجع واحد)
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn('reviewer_id');

            // إضافة الأعمدة الجديدة (التي تقبل عدة مراجعين)
            $table->json('reviewer_ids')->nullable()->after('designer_id');

            // عمود لتخزين حالة كل مراجع (مثال: {"5": "معتمد", "9": "مرفوض"})
            $table->json('reviewers_statuses')->nullable()->after('reviewer_ids');
        });
    }

    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn(['reviewer_ids', 'reviewers_statuses']);
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained()->cascadeOnDelete();

            // تاريخ اليوم (الذي سيتم بناء الصفوف الفارغة على أساسه)
            $table->date('target_date');

            // 1. الإدارة والتكليفات
            $table->foreignId('designer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('deadline')->nullable(); // موعد التسليم

            // 2. حالة المنشور والنشر
            $table->string('actual_publish_status')->default('لم يتم'); // النشر الفعلي
            $table->string('publishing_time')->nullable(); // توقيت النشر
            $table->string('publishing_platform')->nullable(); // منصة النشر

            // 3. موقف التمويل
            $table->string('finance_status')->default('غير ممول'); // ممول / غير ممول
            $table->json('ad_platform')->nullable(); // منصة الاعلان (مصفوفة JSON لاختيار أكثر من منصة)

            // 4. محتوى المنشور
            $table->string('post_type')->nullable(); // نوع المنشور (Infograph, Video, etc.)
            $table->string('objective')->nullable(); // الهدف
            $table->text('detailed_idea')->nullable(); // شرح الفكرة تفصيليا
            $table->text('caption')->nullable(); // Caption
            $table->string('tov')->nullable(); // نبرة الصوت TOV
            $table->string('call_to_action')->nullable(); // Call to Action
            $table->text('hashtags')->nullable(); // Hashtag
            $table->text('reference_link')->nullable(); // Reference Link
            $table->text('content_links')->nullable(); // روابط المحتوى (مكتوب - ديزاين - فيديو)
            $table->text('notes')->nullable(); // Note

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_posts');
    }
};

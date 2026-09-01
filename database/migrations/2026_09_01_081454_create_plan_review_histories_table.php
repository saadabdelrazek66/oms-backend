<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_review_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained()->cascadeOnDelete();

            // المراجع الذي قام بالحركة
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();

            // الإجراء: (approved = مقبول، rejected = مرفوض)
            $table->string('action');

            // سبب الرفض أو الملاحظات
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_review_histories');
    }
};

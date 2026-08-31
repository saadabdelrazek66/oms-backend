<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_plans', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('plan_type');

            // مواعيد التسليم الفعلي (للمدير)
            $table->dateTime('planned_delivery_date');
            $table->dateTime('actual_delivery_date')->nullable();

            // مواعيد المراجعة والتسليم للمختص
            $table->dateTime('planned_review_date');
            $table->dateTime('actual_review_date')->nullable();

            // تفاصيل الخطة
            $table->string('final_link')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plans');
    }
};

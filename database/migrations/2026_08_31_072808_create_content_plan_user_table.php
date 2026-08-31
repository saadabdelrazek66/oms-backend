<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_plan_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // تحديد دور الموظف في هذه الخطة تحديداً
            $table->string('task_role');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plan_user');
    }
};

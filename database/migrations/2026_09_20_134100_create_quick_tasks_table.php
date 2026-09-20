<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quick_tasks', function (Blueprint $table) {
            $table->id();
            
            // العلاقات
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // المدير الذي أنشأ المهمة
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete(); // الموظف المنفذ
            
            $table->string('title')->nullable(); // اختياري لأن المهمة قد تكون مجرد تسجيل صوتي
            $table->text('description')->nullable(); // النص
            $table->string('voice_record_path')->nullable(); // مسار التسجيل الصوتي
            
            $table->dateTime('deadline');
            $table->enum('status', ['pending', 'in_progress', 'under_review', 'rejected', 'completed'])->default('pending');
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('quick_tasks');
    }
};
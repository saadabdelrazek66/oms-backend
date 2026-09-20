<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quick_task_feedback_logs', function (Blueprint $table) {
            $table->id();
            
            // العلاقات
            $table->foreignId('quick_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete(); // من قام بالمراجعة
            
            // الفيدباك (يمكن أن يكون نصاً أو صوتاً)
            $table->text('feedback_text')->nullable();
            $table->string('voice_record_path')->nullable(); 
            
            // الحالة التي تحولت إليها المهمة بسبب هذه المراجعة (غالباً rejected أو completed)
            $table->string('status_changed_to'); 
            
            $table->timestamps(); // يعمل كتاريخ لتسجيل وقت الرفض/الموافقة
        });
    }

    public function down()
    {
        Schema::dropIfExists('quick_task_feedback_logs');
    }
};
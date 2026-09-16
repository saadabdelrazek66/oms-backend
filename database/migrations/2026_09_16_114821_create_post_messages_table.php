<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('post_messages', function (Blueprint $table) {
            $table->id();
            
            // الربط مع المنشور والمستخدم
            $table->foreignId('plan_post_id')->constrained('plan_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // محتوى الرسالة (جعلناه يقبل Null لأن الرسالة قد تكون مجرد ملف صوتي بدون نص)
            $table->text('content')->nullable(); 
            
            // نوع الرسالة لمعرفة كيف نعرضها في الفرونت إند (نص، صورة، فيديو، ملف صوتي، أو رسالة نظام)
            $table->enum('type', ['text', 'image', 'video', 'audio', 'system'])->default('text');
            
            // مسار الملف المرفوع (إن وجد)
            $table->string('file_path', 1000)->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('post_messages');
    }
};
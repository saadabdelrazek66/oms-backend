<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // ربط المهمة بالمشروع
            $table->foreignId('project_id')->constrained()->onDelete('cascade');

            // من قام بإنشاء المهمة (المدير أو الموظف نفسه)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // من هو المسؤول عن تنفيذ المهمة
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();

            // حالة المهمة (مفاتيح إنجليزية لضمان عمل الـ Drag & Drop بامتياز)
            $table->enum('status', ['todo', 'in_progress', 'in_review', 'completed'])->default('todo');

            // أولوية المهمة
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            $table->date('due_date')->nullable(); // تاريخ الاستحقاق

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

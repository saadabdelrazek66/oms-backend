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
        Schema::table('quick_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('quick_tasks', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('voice_record_path');
            }
        });

        Schema::table('quick_task_feedback_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('quick_task_feedback_logs', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('voice_record_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quick_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('quick_tasks', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });

        Schema::table('quick_task_feedback_logs', function (Blueprint $table) {
            if (Schema::hasColumn('quick_task_feedback_logs', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });
    }
};

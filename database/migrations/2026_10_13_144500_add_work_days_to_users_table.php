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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'work_start_day')) {
                $table->string('work_start_day', 20)->nullable()->default('saturday')->after('daily_work_hours');
            }
            if (!Schema::hasColumn('users', 'work_end_day')) {
                $table->string('work_end_day', 20)->nullable()->default('thursday')->after('work_start_day');
            }
            if (!Schema::hasColumn('users', 'working_days')) {
                $table->json('working_days')->nullable()->after('work_end_day');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(['work_start_day', 'work_end_day', 'working_days'], fn($c) => Schema::hasColumn('users', $c));
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

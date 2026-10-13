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
            if (!Schema::hasColumn('users', 'work_start_time')) {
                $table->time('work_start_time')->nullable()->default('09:00:00')->after('work_type');
            }
            if (!Schema::hasColumn('users', 'work_end_time')) {
                $table->time('work_end_time')->nullable()->default('17:00:00')->after('work_start_time');
            }
            if (!Schema::hasColumn('users', 'daily_work_hours')) {
                $table->decimal('daily_work_hours', 4, 2)->nullable()->default(8.00)->after('work_end_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(['work_start_time', 'work_end_time', 'daily_work_hours'], fn($c) => Schema::hasColumn('users', $c));
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

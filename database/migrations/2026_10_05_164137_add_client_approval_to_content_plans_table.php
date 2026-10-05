<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->timestamp('client_approved_at')->nullable()->after('allow_client_notify_by_employee');
        });

        // تعيين تاريخ الاعتماد للخطط المكتملة سابقاً للحفاظ على اتساق البيانات التاريخية
        DB::table('content_plans')
            ->where('status', 'completed')
            ->whereNull('client_approved_at')
            ->update([
                'client_approved_at' => DB::raw('COALESCE(actual_delivery_date, updated_at, NOW())')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn('client_approved_at');
        });
    }
};

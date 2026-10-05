<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->index(['deleted_at', 'id'], 'cp_deleted_id_idx');
            $table->index(['status', 'deleted_at'], 'cp_status_deleted_idx');
            $table->index(['client_id', 'deleted_at'], 'cp_client_deleted_idx');
            $table->index(['planned_delivery_date', 'status'], 'cp_delivery_status_idx');
        });

        Schema::table('content_plan_user', function (Blueprint $table) {
            $table->index(['user_id', 'content_plan_id'], 'cpu_user_plan_idx');
        });

        Schema::table('plan_review_histories', function (Blueprint $table) {
            $table->index(['content_plan_id', 'created_at'], 'prh_plan_created_idx');
        });

        Schema::table('client_follow_ups', function (Blueprint $table) {
            $table->index(['content_plan_id', 'created_at'], 'cfu_plan_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropIndex('cp_deleted_id_idx');
            $table->dropIndex('cp_status_deleted_idx');
            $table->dropIndex('cp_client_deleted_idx');
            $table->dropIndex('cp_delivery_status_idx');
        });

        Schema::table('content_plan_user', function (Blueprint $table) {
            $table->dropIndex('cpu_user_plan_idx');
        });

        Schema::table('plan_review_histories', function (Blueprint $table) {
            $table->dropIndex('prh_plan_created_idx');
        });

        Schema::table('client_follow_ups', function (Blueprint $table) {
            $table->dropIndex('cfu_plan_created_idx');
        });
    }
};

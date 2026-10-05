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
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->index(['content_plan_id', 'target_date'], 'pp_plan_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropIndex('pp_plan_date_idx');
        });
    }
};

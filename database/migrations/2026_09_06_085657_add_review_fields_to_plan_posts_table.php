<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->string('review_status')->default('قيد الانتظار')->after('reviewer_id');

            $table->json('rejection_history')->nullable()->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'rejection_history']);
        });
    }
};

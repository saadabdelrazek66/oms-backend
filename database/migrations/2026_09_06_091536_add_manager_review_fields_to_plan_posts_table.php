<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->string('manager_review_status')->default('قيد الانتظار')->after('review_status');
            $table->json('manager_rejection_history')->nullable()->after('manager_review_status');
        });
    }

    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn(['manager_review_status', 'manager_rejection_history']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->text('delivery_links')->nullable()->after('reviewer_ids');
            $table->dateTime('delivered_at')->nullable()->after('delivery_links');
        });
    }

    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn(['delivery_links', 'delivered_at']);
        });
    }
};

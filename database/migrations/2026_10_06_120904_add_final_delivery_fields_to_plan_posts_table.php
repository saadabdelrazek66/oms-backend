<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->text('final_delivery_link')->nullable()->after('delivered_at');
            $table->dateTime('final_delivered_at')->nullable()->after('final_delivery_link');
        });
    }

    public function down(): void
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn(['final_delivery_link', 'final_delivered_at']);
        });
    }
};
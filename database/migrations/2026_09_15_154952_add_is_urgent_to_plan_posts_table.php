<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            // إضافة عمود عاجل بوضع افتراضي "غير عاجل" (false)
            $table->boolean('is_urgent')->default(false)->after('target_date');
        });
    }

    public function down()
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn('is_urgent');
        });
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->date('planned_initial_delivery_date')->nullable()->after('end_date');
            $table->timestamp('actual_initial_delivery_date')->nullable()->after('planned_initial_delivery_date');
        });
    }

    public function down()
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn(['planned_initial_delivery_date', 'actual_initial_delivery_date']);
        });
    }
};
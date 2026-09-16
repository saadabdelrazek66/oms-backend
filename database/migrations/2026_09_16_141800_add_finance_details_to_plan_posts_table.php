<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            // تكلفة التمويل (تقبل الكسور مثل 150.50)
            $table->decimal('finance_cost', 10, 2)->nullable()->after('finance_status');
            
            // عدد أيام التمويل (أرقام صحيحة فقط)
            $table->integer('finance_days')->nullable()->after('finance_cost');
        });
    }

    public function down()
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn(['finance_cost', 'finance_days']);
        });
    }
};
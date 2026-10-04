<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained('content_plans')->cascadeOnDelete();
            $table->foreignId('plan_item_estimate_id')->nullable()->constrained('plan_item_estimates')->nullOnDelete();
            $table->string('item_name');
            $table->integer('quantity')->default(1);
            $table->decimal('hours_per_unit', 5, 2)->default(0.00);
            $table->decimal('total_hours', 7, 2)->default(0.00);
            $table->string('unit')->default('hour');
            $table->timestamps();
        });

        if (!Schema::hasColumn('content_plans', 'total_estimated_hours')) {
            Schema::table('content_plans', function (Blueprint $table) {
                $table->decimal('total_estimated_hours', 7, 2)->default(0.00)->after('plan_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plan_items');

        if (Schema::hasColumn('content_plans', 'total_estimated_hours')) {
            Schema::table('content_plans', function (Blueprint $table) {
                $table->dropColumn('total_estimated_hours');
            });
        }
    }
};

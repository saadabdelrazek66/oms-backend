<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_item_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('plan_item_categories')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('estimated_hours', 5, 2)->default(0.00);
            $table->string('unit')->default('hour');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_item_estimates');
        Schema::dropIfExists('plan_item_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

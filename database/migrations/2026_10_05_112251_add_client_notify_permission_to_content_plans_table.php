<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->boolean('allow_client_notify_by_employee')->default(false)->after('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn('allow_client_notify_by_employee');
        });
    }
};

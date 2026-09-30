<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('status');
            $table->timestamp('last_recurrence_handled_at')->nullable()->after('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'last_recurrence_handled_at']);
        });
    }
};

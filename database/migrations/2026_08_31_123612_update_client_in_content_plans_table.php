<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            // حذف الحقل النصي القديم
            $table->dropColumn('client_name');
            // إضافة حقل الربط الجديد بعد الـ id مباشرة
            $table->foreignId('client_id')->after('id')->constrained('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->string('client_name')->after('id');
        });
    }
};

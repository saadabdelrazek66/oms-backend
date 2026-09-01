<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // مثل: Facebook, Instagram, WordPress
            $table->string('login_url')->nullable(); // رابط تسجيل الدخول
            $table->string('username'); // اسم المستخدم أو الإيميل
            $table->text('password'); // سيتم تشفيرها تلقائياً
            $table->text('two_factor_notes')->nullable(); // ملاحظات للمصادقة الثنائية
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_credentials');
    }
};

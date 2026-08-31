<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('instapay')->nullable();
            $table->string('wallet')->nullable();

            // تخزين روابط السوشيال ميديا كـ JSON لسهولة إضافة أي عدد من الروابط
            $table->json('social_links')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('client_drive_links', function (Blueprint $table) {
            $table->id();
            // ربط بالعميل، وفي حالة حذف العميل يتم حذف روابطه تلقائياً لعدم تكديس الداتا
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();

            $table->string('title'); // اسم الرابط (مثال: تصميمات السوشيال ميديا)
            $table->string('url', 1000); // الرابط الفعلي (جعلنا المساحة 1000 لأن روابط درايف أحياناً تكون طويلة)

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('client_drive_links');
    }
};

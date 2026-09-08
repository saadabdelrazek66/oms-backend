<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->json('published_links')->nullable()->after('actual_publish_status');
        });
    }

    public function down()
    {
        Schema::table('plan_posts', function (Blueprint $table) {
            $table->dropColumn('published_links');
        });
    }
};

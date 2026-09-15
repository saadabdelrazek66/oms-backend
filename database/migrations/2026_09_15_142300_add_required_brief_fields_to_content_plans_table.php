<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->json('required_brief_fields')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn('required_brief_fields');
        });
    }
};

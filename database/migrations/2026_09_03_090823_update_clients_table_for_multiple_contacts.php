<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إضافة الحقول الجديدة
        Schema::table('clients', function (Blueprint $table) {
            $table->json('phones')->nullable()->after('name');
            $table->json('emails')->nullable()->after('phones');
            $table->string('bank_name')->nullable()->after('bank_account');
            $table->string('bank_branch')->nullable()->after('bank_name');
        });

        DB::table('clients')->orderBy('id')->chunk(100, function ($clients) {
            foreach ($clients as $client) {
                $phones = [];
                if (!empty($client->phone)) {
                    $phones[] = [
                        'number' => $client->phone,
                        'has_whatsapp' => false // افتراضياً غير محدد
                    ];
                }

                $emails = [];
                if (!empty($client->email)) {
                    $emails[] = $client->email;
                }

                // تحديث السجل
                DB::table('clients')->where('id', $client->id)->update([
                    'phones' => json_encode($phones),
                    'emails' => json_encode($emails),
                ]);
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
        });

        DB::table('clients')->orderBy('id')->chunk(100, function ($clients) {
            foreach ($clients as $client) {
                $phones = json_decode($client->phones, true);
                $emails = json_decode($client->emails, true);

                DB::table('clients')->where('id', $client->id)->update([
                    'phone' => !empty($phones) ? $phones[0]['number'] : null,
                    'email' => !empty($emails) ? $emails[0] : null,
                ]);
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['phones', 'emails', 'bank_name', 'bank_branch']);
        });
    }
};

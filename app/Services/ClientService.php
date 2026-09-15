<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ClientService
{
    // إضافة عميل جديد مع جهات الاتصال وروابط درايف
    public function createClient(array $data)
    {
        return DB::transaction(function () use ($data) {
            $client = Client::create($data);

            // إضافة جهات الاتصال إن وجدت
            if (!empty($data['contacts'])) {
                $client->contacts()->createMany($data['contacts']);
            }

            // 🔴 إضافة روابط درايف إن وجدت
            if (!empty($data['drive_links'])) {
                $client->driveLinks()->createMany($data['drive_links']);
            }

            return $client->load(['contacts', 'driveLinks']);
        });
    }

    // تعديل بيانات العميل وجهات اتصاله وروابط درايف
    public function updateClient(Client $client, array $data)
    {
        return DB::transaction(function () use ($client, $data) {
            $client->update($data);

            // تحديث جهات الاتصال
            if (isset($data['contacts'])) {
                $client->contacts()->delete();
                $client->contacts()->createMany($data['contacts']);
            }

            // 🔴 تحديث روابط درايف (مزامنة ذكية للحفاظ على سجلات المراقبة Logs)
            if (isset($data['drive_links'])) {
                $driveLinks = $data['drive_links'];

                // 1. استخراج الـ IDs للروابط القادمة (لتجاهل الروابط الجديدة التي ليس لها ID بعد)
                $incomingIds = collect($driveLinks)->pluck('id')->filter()->toArray();

                // 2. حذف الروابط القديمة التي تم إزالتها من الفرونت إند
                $client->driveLinks()->whereNotIn('id', $incomingIds)->delete();

                // 3. إضافة أو تحديث الروابط
                foreach ($driveLinks as $linkData) {
                    if (isset($linkData['id']) && $linkData['id']) {
                        // تحديث رابط موجود
                        $client->driveLinks()->where('id', $linkData['id'])->update([
                            'title' => $linkData['title'],
                            'url'   => $linkData['url'],
                        ]);
                    } else {
                        // إضافة رابط جديد
                        $client->driveLinks()->create([
                            'title' => $linkData['title'],
                            'url'   => $linkData['url'],
                        ]);
                    }
                }
            }

            return $client->load(['contacts', 'driveLinks']);
        });
    }
}

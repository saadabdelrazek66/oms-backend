<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;

class ClientService
{
    // إضافة عميل جديد مع جهات الاتصال وروابط درايف واللوجو
    public function createClient(array $data)
    {
        // معالجة رفع اللوجو قبل الدخول في الـ Transaction
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $file = $data['logo'];
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/clients'), $filename);
            $data['logo'] = $filename;
        }

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

    // تعديل بيانات العميل وجهات اتصاله وروابط درايف واللوجو
    public function updateClient(Client $client, array $data)
    {
        // معالجة رفع اللوجو الجديد ومسح القديم
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            // مسح الصورة القديمة من السيرفر لو موجودة
            if ($client->logo && File::exists(public_path('uploads/clients/' . $client->logo))) {
                File::delete(public_path('uploads/clients/' . $client->logo));
            }

            // رفع الصورة الجديدة
            $file = $data['logo'];
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/clients'), $filename);
            $data['logo'] = $filename; // تحديث المصفوفة باسم الملف الجديد
        }

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
<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ClientService
{
    // إضافة عميل جديد مع جهات الاتصال الخاصة به
    public function createClient(array $data)
    {
        return DB::transaction(function () use ($data) {
            $client = Client::create($data);

            // إذا كان هناك جهات اتصال مرسلة، نقوم بإضافتها
            if (!empty($data['contacts'])) {
                $client->contacts()->createMany($data['contacts']);
            }

            return $client->load('contacts');
        });
    }

    // تعديل بيانات العميل وجهات اتصاله
    public function updateClient(Client $client, array $data)
    {
        return DB::transaction(function () use ($client, $data) {
            $client->update($data);

            if (isset($data['contacts'])) {
                $client->contacts()->delete();
                $client->contacts()->createMany($data['contacts']);
            }

            return $client->load('contacts');
        });
    }
}

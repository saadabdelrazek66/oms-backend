<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;
use App\Http\Requests\ClientRequest;

class ClientController extends Controller
{
    public function __construct(private ClientService $service) {}

    // عرض كل العملاء مع جهات الاتصال الخاصة بهم
    public function index()
    {
        $clients = Client::with('contacts')->orderBy('id', 'desc')->paginate(15);
        return response()->json($clients);
    }

    // إضافة عميل جديد
    public function store(ClientRequest $request)
    {
        $client = $this->service->createClient($request->validated());

        return response()->json([
            'message' => 'تم إضافة العميل بنجاح',
            'data' => $client
        ], 201);
    }

    // تعديل بيانات عميل
    public function update(ClientRequest $request, Client $client)
    {
        $updatedClient = $this->service->updateClient($client, $request->validated());

        return response()->json([
            'message' => 'تم تعديل العميل بنجاح',
            'data' => $updatedClient
        ]);
    }

    // حذف العميل (بفضل cascadeOnDelete في الداتابيز، سيتم حذف جهات الاتصال التابعة له تلقائياً)
    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json(['message' => 'تم حذف العميل بنجاح']);
    }
}

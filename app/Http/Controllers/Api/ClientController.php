<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;
use App\Http\Requests\ClientRequest;
use Illuminate\Support\Facades\File;

class ClientController extends Controller
{
    public function __construct(private ClientService $service) {}

    // عرض كل العملاء مع جهات الاتصال وروابط درايف الخاصة بهم
    public function index(Request $request)
    {
        $perPage = min(100, max(5, (int)$request->input('per_page', 15)));
        $clients = Client::with([
            'contacts:id,client_id,contact_name,contact_method,contact_details',
            'driveLinks:id,client_id,title,url'
        ])
        ->orderBy('id', 'desc')
        ->paginate($perPage);

        return response()->json($clients);
    }

    // إضافة عميل جديد
    public function store(ClientRequest $request)
    {
        // $request->validated() ستمرر ملف اللوجو تلقائياً للسيرفيس
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

    // حذف العميل
    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json(['message' => 'تم حذف العميل بنجاح']);
    }
}
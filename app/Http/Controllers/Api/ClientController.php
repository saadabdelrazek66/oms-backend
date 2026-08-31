<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'bank_account' => 'nullable|string|max:255',
            'instapay' => 'nullable|string|max:255',
            'wallet' => 'nullable|string|max:255',
            'social_links' => 'nullable|array', // التحقق من أن السوشيال ميديا عبارة عن مصفوفة
            'contacts' => 'nullable|array', // التحقق من أن جهات الاتصال عبارة عن مصفوفة

            // التحقق من كل عنصر داخل مصفوفة جهات الاتصال
            'contacts.*.contact_name' => 'required|string|max:255',
            'contacts.*.contact_method' => 'required|string|max:255',
            'contacts.*.contact_details' => 'required|string|max:255',
        ]);

        $client = $this->service->createClient($validated);
        return response()->json(['message' => 'تم إضافة العميل بنجاح', 'data' => $client], 201);
    }

    // تعديل بيانات عميل
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'bank_account' => 'nullable|string|max:255',
            'instapay' => 'nullable|string|max:255',
            'wallet' => 'nullable|string|max:255',
            'social_links' => 'nullable|array',
            'contacts' => 'nullable|array',
            'contacts.*.contact_name' => 'required|string|max:255',
            'contacts.*.contact_method' => 'required|string|max:255',
            'contacts.*.contact_details' => 'required|string|max:255',
        ]);

        $updatedClient = $this->service->updateClient($client, $validated);
        return response()->json(['message' => 'تم تعديل العميل بنجاح', 'data' => $updatedClient]);
    }

    // حذف العميل (بفضل cascadeOnDelete في الداتابيز، سيتم حذف جهات الاتصال التابعة له تلقائياً)
    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json(['message' => 'تم حذف العميل بنجاح']);
    }
}

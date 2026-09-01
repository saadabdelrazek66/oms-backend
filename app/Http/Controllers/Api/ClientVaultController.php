<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientVaultController extends Controller
{
    // 1. جلب حالة الخزنة (هل المدير لديه PIN مسجل أم لا؟)
    public function status(Request $request)
    {
        if ($request->user()->role->value !== 'manager') abort(403);

        return response()->json([
            'has_pin' => !is_null($request->user()->vault_pin)
        ]);
    }

    // 2. إعداد الرقم السري لأول مرة
    public function setupPin(Request $request)
    {
        if ($request->user()->role->value !== 'manager') abort(403);

        $request->validate(['pin' => 'required|digits:4']);

        $user = $request->user();
        $user->vault_pin = Hash::make($request->pin); // تشفير قوي للـ PIN
        $user->save();

        return response()->json(['message' => 'تم تعيين الرقم السري للخزنة بنجاح']);
    }

    // 3. التحقق من الرقم السري (تُستخدم لفتح الواجهة مبدئياً)
    public function verifyPin(Request $request)
    {
        if ($request->user()->role->value !== 'manager') abort(403);
        $request->validate(['pin' => 'required|digits:4']);

        if (!Hash::check($request->pin, $request->user()->vault_pin)) {
            return response()->json(['message' => 'الرقم السري غير صحيح'], 403);
        }

        return response()->json(['message' => 'تم التحقق بنجاح', 'success' => true]);
    }

    // --- دالة مساعدة جبارة لحماية كل بيانات الخزنة ---
    private function ensureVaultUnlocked(Request $request)
    {
        if ($request->user()->role->value !== 'manager') {
            abort(403, 'غير مصرح لك بالدخول');
        }

        // الباك إند لن يقبل أي عملية إلا إذا كان الـ PIN مبعوثاً في الـ Headers وصحيحاً
        $pin = $request->header('X-Vault-Pin');
        if (!$pin || !Hash::check($pin, $request->user()->vault_pin)) {
            abort(403, 'الرقم السري للخزنة مفقود أو غير صحيح');
        }
    }

    // 4. جلب بيانات الخزنة (العملاء بحساباتهم)
    public function index(Request $request)
    {
        $this->ensureVaultUnlocked($request);

        // نجلب العملاء مع حساباتهم السرية
        $clients = Client::with('credentials')->get();
        return response()->json(['data' => $clients]);
    }

    // 5. إضافة حساب جديد لعميل
    public function store(Request $request, Client $client)
    {
        $this->ensureVaultUnlocked($request);

        $validated = $request->validate([
            'platform' => 'required|string',
            'login_url' => 'nullable|url',
            'username' => 'required|string',
            'password' => 'required|string',
            'two_factor_notes' => 'nullable|string',
        ]);

        $credential = $client->credentials()->create($validated);
        return response()->json(['message' => 'تم حفظ البيانات في الخزنة بنجاح', 'data' => $credential]);
    }

    // 6. تعديل حساب موجود
    public function update(Request $request, ClientCredential $credential)
    {
        $this->ensureVaultUnlocked($request);

        $validated = $request->validate([
            'platform' => 'required|string',
            'login_url' => 'nullable|url',
            'username' => 'required|string',
            'password' => 'required|string',
            'two_factor_notes' => 'nullable|string',
        ]);

        $credential->update($validated);
        return response()->json(['message' => 'تم التحديث بنجاح', 'data' => $credential]);
    }

    // 7. حذف حساب
    public function destroy(Request $request, ClientCredential $credential)
    {
        $this->ensureVaultUnlocked($request);

        $credential->delete();
        return response()->json(['message' => 'تم الحذف من الخزنة بنجاح']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientCredentialRequest;
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

    public function index(Request $request)
    {
        $this->ensureVaultUnlocked($request);
        $clients = Client::with('credentials')->get();
        $jsonData = json_encode($clients);

        // المفتاح الجديد (32 حرف)
        $key = env('VAULT_SECRET_KEY', 'OctoSpaceSecureVaultKey2026!@#$*');
        $method = 'aes-256-cbc';

        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);

        // التعديل هنا: إضافة OPENSSL_RAW_DATA
        $encrypted = openssl_encrypt($jsonData, $method, $key, OPENSSL_RAW_DATA, $iv);

        return response()->json([
            'encrypted' => true,
            'payload' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ]);
    }

    // 5. إضافة حساب جديد لعميل
    public function store(ClientCredentialRequest $request, Client $client)
    {
        // 1. التحقق من أمان الخزنة (الرقم السري للمدير)
        $this->ensureVaultUnlocked($request);

        // 2. حفظ البيانات بعد التأكد من صحتها (Validation)
        $credential = $client->credentials()->create($request->validated());

        return response()->json([
            'message' => 'تم حفظ البيانات المشفرة في الخزنة بنجاح',
            'data' => $credential
        ], 201);
    }

    // 6. تعديل حساب موجود
    public function update(ClientCredentialRequest $request, ClientCredential $credential)
    {
        // 1. التحقق من أمان الخزنة (الرقم السري للمدير)
        $this->ensureVaultUnlocked($request);

        // 2. تحديث البيانات
        $credential->update($request->validated());

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'data' => $credential
        ]);
    }

    // 7. حذف حساب
    public function destroy(Request $request, ClientCredential $credential)
    {
        $this->ensureVaultUnlocked($request);

        $credential->delete();
        return response()->json(['message' => 'تم الحذف من الخزنة بنجاح']);
    }
}

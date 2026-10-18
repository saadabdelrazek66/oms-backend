<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientCredentialRequest;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientVaultController extends Controller
{
    // 1. جلب حالة الخزنة
    public function status(Request $request)
    {
        if (!$request->user()->isManager() && !$request->user()->hasPermission('vault.view') && !$request->user()->hasPermission('vault.manage')) {
            return response()->json(['message' => 'غير مصرح لك بالوصول لخزنة العملاء.'], 403);
        }

        $manager = User::where('role', 'manager')->whereNotNull('vault_pin')->first();
        $hasPin = !is_null($request->user()->vault_pin) || ($manager && !is_null($manager->vault_pin));

        return response()->json([
            'has_pin' => $hasPin
        ]);
    }

    // 2. إعداد الرقم السري
    public function setupPin(Request $request)
    {
        if (!$request->user()->isManager() && !$request->user()->hasPermission('vault.manage')) {
            return response()->json(['message' => 'غير مصرح لك بإعداد الرقم السري للخزنة.'], 403);
        }

        $request->validate(['pin' => 'required|digits:4']);

        $user = $request->user();
        $user->vault_pin = Hash::make($request->pin);
        $user->save();

        return response()->json(['message' => 'تم تعيين الرقم السري للخزنة بنجاح']);
    }

    // 3. التحقق من الرقم السري
    public function verifyPin(Request $request)
    {
        if (!$request->user()->isManager() && !$request->user()->hasPermission('vault.view')) {
            return response()->json(['message' => 'غير مصرح لك بالوصول لخزنة العملاء.'], 403);
        }

        $request->validate(['pin' => 'required|digits:4']);

        $manager = User::where('role', 'manager')->whereNotNull('vault_pin')->first();
        $targetPinHash = $request->user()->vault_pin ?? ($manager ? $manager->vault_pin : null);

        if (!$targetPinHash || !Hash::check($request->pin, $targetPinHash)) {
            return response()->json(['message' => 'الرقم السري غير صحيح'], 403);
        }

        return response()->json(['message' => 'تم التحقق بنجاح', 'success' => true]);
    }

    // --- دالة مساعدة لحماية بيانات الخزنة ---
    private function ensureVaultUnlocked(Request $request)
    {
        if (!$request->user()->isManager() && !$request->user()->hasPermission('vault.view') && !$request->user()->hasPermission('vault.manage')) {
            abort(403, 'غير مصرح لك بالوصول لبيانات الخزنة.');
        }

        $manager = User::where('role', 'manager')->whereNotNull('vault_pin')->first();
        $targetPinHash = $request->user()->vault_pin ?? ($manager ? $manager->vault_pin : null);

        $pin = $request->header('X-Vault-Pin');
        if (!$pin || !$targetPinHash || !Hash::check($pin, $targetPinHash)) {
            abort(403, 'الرقم السري للخزنة مفقود أو غير صحيح');
        }
    }

    public function index(Request $request)
    {
        $this->ensureVaultUnlocked($request);
        $clients = Client::with('credentials')->get();
        $jsonData = json_encode($clients);

        $key = env('VAULT_SECRET_KEY', 'OctoSpaceSecureVaultKey2026!@#$*');
        $method = 'aes-256-cbc';

        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);

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
        if (!$request->user()->isManager() && !$request->user()->hasPermission('vault.manage')) {
            return response()->json(['message' => 'غير مصرح لك بإضافة حسابات للخزنة.'], 403);
        }

        $this->ensureVaultUnlocked($request);
        $credential = $client->credentials()->create($request->validated());

        return response()->json([
            'message' => 'تم حفظ البيانات المشفرة في الخزنة بنجاح',
            'data' => $credential
        ], 201);
    }

    // 6. تعديل حساب موجود
    public function update(ClientCredentialRequest $request, ClientCredential $credential)
    {
        if (!$request->user()->isManager() && !$request->user()->hasPermission('vault.manage')) {
            return response()->json(['message' => 'غير مصرح لك بتعديل حسابات الخزنة.'], 403);
        }

        $this->ensureVaultUnlocked($request);
        $credential->update($request->validated());

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'data' => $credential
        ]);
    }

    // 7. حذف حساب
    public function destroy(Request $request, ClientCredential $credential)
    {
        if (!$request->user()->isManager() && !$request->user()->hasPermission('vault.manage')) {
            return response()->json(['message' => 'غير مصرح لك بحذف حسابات من الخزنة.'], 403);
        }

        $this->ensureVaultUnlocked($request);
        $credential->delete();
        return response()->json(['message' => 'تم الحذف من الخزنة بنجاح']);
    }
}

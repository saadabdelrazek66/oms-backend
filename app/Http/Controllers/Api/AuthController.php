<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    // حقن الـ AuthService باستخدام الـ Constructor Property Promotion
    public function __construct(private AuthService $authService) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // استدعاء المنطق من الـ Service
        $result = $this->authService->login($request->only('email', 'password'));

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => $result
        ]);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }
}

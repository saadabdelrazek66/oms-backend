<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login(
            $request->only('email', 'password'),
            $request->boolean('remember')
        );

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

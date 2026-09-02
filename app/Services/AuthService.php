<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(array $credentials, bool $remember = false)
    {
        if (!auth()->attempt($credentials)) {
            abort(401, 'البريد الإلكتروني أو كلمة المرور غير صحيحة.');
        }

        $user = auth()->user();

        $expiration = $remember ? now()->addYears(1) : now()->addHours(12);

        $token = $user->createToken('auth_token', ['*'], $expiration)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout($user)
    {
        $user->currentAccessToken()->delete();
    }
}

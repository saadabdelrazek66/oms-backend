<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'غير مصرح لك بالوصول (يجب تسجيل الدخول).'
            ], 401);
        }

        // المدير يمتلك كافة الصلاحيات دائماً دون أي قيود
        if ($user->isManager()) {
            return $next($request);
        }

        // التحقق مما إذا كان لدى الموظف أي من الصلاحيات المطلوبة
        if ($user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'عذراً، لا تمتلك الصلاحية اللازمة للقيام بهذا الإجراء أو الوصول لهذه الصفحة.',
            'required_permissions' => $permissions
        ], 403);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SettingService;
use Symfony\Component\HttpFoundation\Response;

class CheckSystemFreeze
{
    /**
     * التحقق من وضع تجميد المنظومة (Emergency System Freeze)
     * في حال تفعيله، يتم تحويل المنظومة لوضع القراءة فقط للموظفين لمنع أي إنشاء أو تعديل مؤقتاً.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. فحص ما إذا كان وضع التجميد مفعلاً في الإعدادات العامة
        try {
            $settingService = app(SettingService::class);
            $isFrozen = (bool) $settingService->get('system_freeze_mode', false);
        } catch (\Throwable $e) {
            $isFrozen = false;
        }

        if (!$isFrozen) {
            return $next($request);
        }

        // 2. العمليات الآمنة للقراءة فقط مسموحة دائماً (GET, HEAD, OPTIONS)
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // 3. استثناء مسارات تسجيل الدخول والخروج
        if ($request->is('api/login') || $request->is('api/logout')) {
            return $next($request);
        }

        // 4. استثناء المدير العام: مسموح له بكافة العمليات وإلغاء التجميد
        $user = $request->user();
        if ($user && ($user->role === 'manager' || (is_object($user->role) && $user->role->value === 'manager'))) {
            return $next($request);
        }

        // 5. حظر أي عملية كتابة / تعديل / حذف للموظفين العاديين أثناء التجميد
        return response()->json([
            'status' => 'error',
            'code' => 'SYSTEM_FROZEN',
            'message' => 'المنظومة في وضع الصيانة والتجميد المؤقت (System Freeze). يتاح فقط تصفح البيانات للقراءة، ويُمنع إجراء أي إنشاء أو تعديل أو حذف مؤقتاً.',
            'system_freeze' => true,
        ], 423); // 423 Locked
    }
}

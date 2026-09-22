<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // التأكد من أن الرد يدعم إضافة الترويسات (Headers)
        if (method_exists($response, 'header')) {
            // يمنع وضع موقعك داخل iframe لحمايتك من هجوم Clickjacking
            $response->header('X-Frame-Options', 'DENY');
            
            // يمنع المتصفح من تخمين نوع الملفات (يحمي من رفع ملفات خبيثة بصيغ مزيفة)
            $response->header('X-Content-Type-Options', 'nosniff');
            
            // يجبر المتصفح على استخدام HTTPS دائماً لمدة سنة كاملة (مهم جداً في الـ Production)
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            
            // يحمي بيانات الـ URL عند الانتقال من موقعك لموقع خارجي
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        return $response;
    }
}
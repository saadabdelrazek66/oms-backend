<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. المستوى العام (للتصفح والداشبورد): 60 طلب في الدقيقة لكل مستخدم (أو IP)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 2. مستوى المصادقة (تسجيل الدخول): 5 طلبات فقط في الدقيقة لمنع تخمين الباسورد
        RateLimiter::for('auth_limit', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // 3. مستوى الرفع (المهام السريعة والصوتيات): 10 طلبات في الدقيقة لمنع إغراق السيرفر بالملفات
        RateLimiter::for('uploads_limit', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'غير مصرح لك بالوصول لهذا المسار'
            ], 401);
        }

        $userRole = is_object($user->role) ? ($user->role->value ?? '') : (string)($user->role ?? '');
        $jobTitle = (string)($user->job_title ?? '');

        foreach ($roles as $role) {
            $roleClean = strtolower(str_replace([' ', '_', '-'], '', trim($role)));

            // 1. مطابقة الدور الأساسي (مثل manager أو employee)
            if (strtolower($userRole) === $roleClean || $userRole === trim($role)) {
                return $next($request);
            }

            // 2. مطابقة المسمى الوظيفي (مثل Account Manager)
            $jobTitleClean = strtolower(str_replace([' ', '_', '-'], '', $jobTitle));
            if ($jobTitleClean !== '' && $jobTitleClean === $roleClean) {
                return $next($request);
            }

            // 3. إذا كان المسار مسموحاً لـ account_manager، فالمدير العام يملك الصلاحية حكماً
            if ($roleClean === 'accountmanager' && $userRole === 'manager') {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'غير مصرح لك بالوصول لهذا المسار'
        ], 403);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

class SystemLogController extends Controller
{
    /**
     * جلب سجلات النظام مع الفلاتر (بما فيها الفلتر بالصلاحية)
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك بالوصول'], 403);
        }

        $query = Activity::with('causer')->latest();

        // فلتر باسم الموظف
        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        // 🔴 الجديد: فلتر بصلاحية المستخدم (مدير / موظف) 🔴
        if ($request->filled('role')) {
            $query->whereHas('causer', function($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        // فلتر الموديول
        if ($request->filled('module')) {
            $query->where('log_name', $request->module);
        }

        // فلتر الحدث
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        // فلتر التاريخ
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        $logs = $query->paginate(20);

        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'description' => $log->description,
                'module' => $log->log_name,
                'event' => $log->event ?? 'custom',
                'causer' => $log->causer ? $log->causer->name : 'النظام',
                'causer_role' => $log->causer ? $log->causer->role : 'system', // إرجاع الدور للفرونت
                'causer_id' => $log->causer_id,
                'properties' => $log->properties,
                'created_at' => $log->created_at->format('Y-m-d h:i:s A'),
                'time_human' => $log->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }

    /**
     * 🔴 الجديد: حذف السجلات (بفترة زمنية أو الكل) 🔴
     */
    public function destroy(Request $request): JsonResponse
    {
        // حماية إضافية: المدير فقط من يحق له الحذف
        if ($request->user()->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك بحذف السجلات'], 403);
        }

        // حالة 1: حذف الكل
        if ($request->boolean('delete_all')) {
            Activity::truncate(); // يمسح الجدول بالكامل ويعيد الترقيم لـ 1
            return response()->json(['message' => 'تم مسح جميع السجلات من النظام بنجاح.']);
        }

        // حالة 2: حذف بفترة زمنية محددة
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $deleted = Activity::whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ])->delete();

            return response()->json([
                'message' => "تم حذف عدد ({$deleted}) سجل في الفترة المحددة بنجاح."
            ]);
        }

        return response()->json(['message' => 'يرجى تحديد فترة زمنية أو اختيار حذف الكل.'], 400);
    }
}

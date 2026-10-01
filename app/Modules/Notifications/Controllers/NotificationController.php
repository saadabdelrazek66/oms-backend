<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{

    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(15);
        return response()->json($notifications);
    }


    public function unreadCount(Request $request)
    {
        $count = $request->user()->unreadNotifications()->count();
        return response()->json(['count' => $count]);
    }


    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->find($id);
        
        if ($notification && $notification->unread()) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'تم تحديد الإشعار كمقروء']);
    }


    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'تم تحديد جميع الإشعارات كمقروءة']);
    }
}
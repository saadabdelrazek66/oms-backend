<?php

namespace App\Events;

use App\Models\PostMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// استخدمنا ShouldBroadcastNow لضمان إرسال الرسالة فوراً بدون تأخير
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(PostMessage $message)
    {
        // تحميل بيانات المرسل لكي يظهر اسمه وصورته في الشات مباشرة
        $this->message = $message->load('user:id,name');
    }

    /**
     * تحديد القناة التي سيتم البث عليها
     */
    public function broadcastOn()
    {
        // قناة خاصة (Private) لكل منشور لحماية خصوصية المحادثة
        return new PrivateChannel('post-chat.' . $this->message->plan_post_id);
    }

    /**
     * اسم الحدث الذي سيستمع له الـ Vue
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }
}
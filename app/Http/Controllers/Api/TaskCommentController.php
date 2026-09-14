<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    // دالة مساعدة مركزية للتحقق من الصلاحيات
    private function canAccessTask(Task $task)
    {
        $user = auth()->user();
        if ($user->role->value === 'manager') return true;
        return $task->assigned_to === $user->id || $task->created_by === $user->id;
    }

    public function index(Task $task)
    {
        // حماية مسار عرض التعليقات
        if (!$this->canAccessTask($task)) {
            return response()->json(['message' => 'غير مصرح لك برؤية محادثات هذه المهمة'], 403);
        }

        $comments = $task->comments()->with('user:id,name')->orderBy('created_at', 'asc')->get();
        return response()->json(['data' => $comments]);
    }

    public function store(Request $request, Task $task)
    {
        // حماية مسار إضافة التعليقات
        if (!$this->canAccessTask($task)) {
            return response()->json(['message' => 'غير مصرح لك بالتعليق على هذه المهمة'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:1000'
        ]);

        $comment = $task->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->comment
        ]);

        return response()->json([
            'message' => 'تمت إضافة التعليق',
            'data' => $comment->load('user:id,name')
        ], 201);
    }

    // تعديل التعليق
    public function update(Request $request, TaskComment $comment)
    {
        // التحقق: صاحب التعليق فقط هو من يحق له التعديل
        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذا التعليق'], 403);
        }

        if ($comment->is_deleted) {
            return response()->json(['message' => 'لا يمكن تعديل تعليق محذوف'], 400);
        }

        $request->validate(['comment' => 'required|string|max:1000']);

        $comment->update([
            'comment' => $request->comment,
            'is_edited' => true
        ]);

        return response()->json([
            'message' => 'تم التعديل',
            'data' => $comment->load('user:id,name')
        ]);
    }

    // حذف التعليق
    public function destroy(TaskComment $comment)
    {
        $user = auth()->user();

        // التحقق: صاحب التعليق أو المدير يحق لهما الحذف
        if ($comment->user_id !== $user->id && $user->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك بحذف هذا التعليق'], 403);
        }

        // مسح محتوى الرسالة برمجياً للحفاظ على الخصوصية، وتفعيل علامة الحذف
        $comment->update([
            'comment' => '🚫 تم حذف هذه الرسالة',
            'is_deleted' => true
        ]);

        return response()->json([
            'message' => 'تم الحذف',
            'data' => $comment->load('user:id,name')
        ]);
    }
}

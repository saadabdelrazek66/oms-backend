<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;

class TaskController extends Controller
{
    public function index(Project $project)
    {
        $user = auth()->user();

        if ($user->role->value !== 'manager' && !$project->users->contains($user->id)) {
            return response()->json(['message' => 'غير مصرح لك برؤية مهام هذا المشروع'], 403);
        }

        $tasks = $project->tasks()->with(['assignee', 'creator'])->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $tasks]);
    }

    public function store(StoreTaskRequest $request, Project $project)
    {
        $validated = $request->validated();
        $user = auth()->user();

        $validated['project_id'] = $project->id;
        $validated['created_by'] = $user->id;

        if ($user->role->value !== 'manager') {
            $validated['assigned_to'] = $user->id;
        }

        $task = Task::create($validated);
        $task->load(['assignee', 'creator', 'project.users']);

        $whatsappLink = null;

        if ($user->role->value === 'manager') {
            // المدير يبلغ الموظف عند إنشاء مهمة مسندة إليه.
            if ($task->assignee?->phone) {
                $message = "مهمة جديدة مسندة إليك!\n\n";
                $message .= "العنوان: {$task->title}\n";
                $message .= "المشروع: {$task->project->name}\n";
                $message .= "الاستحقاق: " . ($task->due_date ?: 'غير محدد');

                $whatsappLink = $this->generateWhatsAppLink(
                    $task->assignee->phone,
                    $message
                );
            }
        } else {
            // الموظف يبلغ المدير عند إنشاء مهمة من جانبه.
            $whatsappLink = $this->employeeNotificationLink($task, 'تم إنشاء مهمة جديدة');
        }

        return response()->json([
            'message' => 'تم إنشاء المهمة بنجاح',
            'data' => $task,
            'whatsapp_link' => $whatsappLink,
        ], 201);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $validated = $request->validated();
        $user = auth()->user();

        if ($user->role->value !== 'manager') {
            unset($validated['assigned_to']);
        }

        $task->update($validated);
        $task->load(['assignee', 'creator', 'project.users']);

        $whatsappLink = null;

        if ($user->role->value !== 'manager') {
            // أي تعديل من الموظف يرسل إشعارًا للمدير، وليس فقط in_review.
            $whatsappLink = $this->employeeNotificationLink($task, 'تم تحديث المهمة من الموظف');
        }

        return response()->json([
            'message' => 'تم تحديث المهمة',
            'data' => $task->load(['assignee', 'creator']),
            'whatsapp_link' => $whatsappLink,
        ]);
    }

    private function employeeNotificationLink(Task $task, string $eventTitle): ?string
    {
        $manager = $this->resolveTaskManager($task);

        if (!$manager?->phone) {
            return null;
        }

        $message = "{$eventTitle}\n\n";
        $message .= "المهمة: {$task->title}\n";
        $message .= "المشروع: {$task->project?->name}\n";
        $message .= "الحالة: " . ($task->status ?: 'غير محددة');

        if ($task->due_date) {
            $message .= "\nالاستحقاق: {$task->due_date}";
        }

        return $this->generateWhatsAppLink($manager->phone, $message);
    }

    private function resolveTaskManager(Task $task)
    {
        // في المهام التي أنشأها المدير يكون creator هو المدير.
        if ($task->creator && $task->creator->role?->value === 'manager') {
            return $task->creator;
        }

        // وإلا نبحث عن مدير ضمن أعضاء المشروع.
        return $task->project?->users?->first(
            fn ($member) => $member->role?->value === 'manager'
        );
    }

    private function generateWhatsAppLink(?string $phone, string $message): ?string
    {
        if (!$phone) {
            return null;
        }

        // الاحتفاظ بالأرقام فقط، ثم إضافة كود مصر عند الحاجة.
        $phone = preg_replace('/\D+/', '', $phone);
        $phone = ltrim($phone, '0');

        if (!str_starts_with($phone, '20')) {
            $phone = '20' . $phone;
        }

        // rawurlencode يحافظ على المسافات والرموز التعبيرية بصورة متوافقة مع wa.me.
        return "https://wa.me/{$phone}?text=" . rawurlencode($message );
    }

    public function destroy(Task $task)
    {
        $user = auth()->user();
        if ($user->role->value !== 'manager' && $task->created_by !== $user->id) {
            return response()->json(['message' => 'لا تملك صلاحية حذف هذه المهمة'], 403);
        }
        $task->delete();
        return response()->json(['message' => 'تم حذف المهمة']);
    }
    
}

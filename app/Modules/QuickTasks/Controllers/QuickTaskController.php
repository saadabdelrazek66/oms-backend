<?php

namespace App\Modules\QuickTasks\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\QuickTasks\Models\QuickTask;
use App\Modules\QuickTasks\Requests\StoreQuickTaskRequest;
use App\Modules\QuickTasks\Requests\ReviewQuickTaskRequest;
use App\Modules\QuickTasks\Services\QuickTaskService;
use App\Modules\QuickTasks\Resources\QuickTaskResource;
use App\Modules\QuickTasks\Resources\FeedbackLogResource;
use App\Modules\QuickTasks\Requests\UpdateQuickTaskRequest;

use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class QuickTaskController extends Controller
{
    use ApiResponseTrait;

    protected $quickTaskService;

    public function __construct(QuickTaskService $quickTaskService)
    {
        $this->quickTaskService = $quickTaskService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $role = is_object($user->role) ? $user->role->value : $user->role;
        $isManager = $role === 'manager' || $user->job_title === 'Account Manager';

        $query = QuickTask::with(['creator', 'assignee', 'feedbackLogs.reviewer']);

        if (!$isManager) {
            $query->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        }

        $tasks = $query->latest()->paginate(50);

        return $this->successResponse(
            QuickTaskResource::collection($tasks)->response()->getData(true),
            'تم جلب المهام بنجاح'
        );
    }

    public function store(StoreQuickTaskRequest $request)
    {
        $task = $this->quickTaskService->createTask(
            $request->validated(),
            $request->file('voice_record'),
            $request->user()->id
        );

        return $this->successResponse(new QuickTaskResource($task), 'تم إنشاء المهمة بنجاح', 201);
    }

    public function submit(Request $request, QuickTask $quickTask)
    {
        $request->validate([
            'feedback_text' => 'nullable|string|max:5000',
            'voice_record' => 'nullable|file|mimes:audio/mpeg,mpga,mp3,wav,ogg,webm,mp4|max:10240',
        ]);

        $task = $this->quickTaskService->submitTask(
            $quickTask,
            $request->only(['feedback_text']),
            $request->file('voice_record'),
            $request->user()->id
        );

        return $this->successResponse(new QuickTaskResource($task), 'تم تسليم المهمة للمراجعة');
    }

    public function review(ReviewQuickTaskRequest $request, QuickTask $quickTask)
    {
        $user = $request->user();
        $role = is_object($user->role) ? $user->role->value : $user->role;

        // الموظف المنفذ لا يملك صلاحية مراجعة أو اعتماد مهمته
        if ((int)$user->id === (int)$quickTask->assigned_to && $role !== 'manager') {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك باعتماد أو رفض هذه المهمة.'
            ], 403);
        }

        $this->quickTaskService->reviewTask(
            $quickTask,
            $request->validated(),
            $request->file('voice_record'),
            $request->user()->id
        );

        $quickTask->load(['creator', 'assignee', 'feedbackLogs.reviewer']);

        return $this->successResponse(new QuickTaskResource($quickTask), 'تم حفظ المراجعة بنجاح');
    }

    public function update(UpdateQuickTaskRequest $request, QuickTask $quickTask)
    {
        $task = $this->quickTaskService->updateTask(
            $quickTask,
            $request->validated(),
            $request->file('voice_record')
        );

        return $this->successResponse(new QuickTaskResource($task), 'تم تعديل المهمة بنجاح');
    }

    public function destroy(Request $request, QuickTask $quickTask)
    {
        $user = $request->user();
        $role = is_object($user->role) ? $user->role->value : $user->role;

        if ((int)$user->id === (int)$quickTask->assigned_to && $role !== 'manager') {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بحذف هذه المهمة'], 403);
        }

        if ($role !== 'manager' && (int)$user->id !== (int)$quickTask->created_by) {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بحذف هذه المهمة'], 403);
        }

        $this->quickTaskService->deleteTask($quickTask);

        return $this->successResponse(null, 'تم حذف المهمة بنجاح');
    }
}
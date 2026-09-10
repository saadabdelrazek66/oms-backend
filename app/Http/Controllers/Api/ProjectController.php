<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // جلب المشاريع بناءً على الصلاحية
    public function index()
    {
        $user = auth()->user();

        if ($user->role->value === 'manager') {
            // المدير يرى كل المشاريع
            $projects = Project::with(['departments', 'users'])->orderBy('created_at', 'desc')->get();
        } else {
            // الموظف يرى فقط المشاريع المصرح له بها
            $projects = $user->projects()->with(['departments', 'users'])->orderBy('created_at', 'desc')->get();
        }

        return response()->json(['data' => $projects]);
    }

    // إنشاء مشروع جديد (الصلاحيات والـ Validation تتم تلقائياً في StoreProjectRequest)
    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();

        // إنشاء المشروع الأساسي
        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'قيد التخطيط',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
        ]);

        // ربط العلاقات بطريقة ذكية (Sync)
        $project->departments()->sync($validated['department_ids']);
        $project->users()->sync($validated['user_ids']);

        return response()->json([
            'message' => 'تم إنشاء المشروع بنجاح.',
            'data' => $project->load(['departments', 'users'])
        ], 201);
    }

    // عرض مشروع واحد
    public function show(Project $project)
    {
        $user = auth()->user();

        // حماية: إذا كان موظفاً ولم يكن ضمن أعضاء المشروع، يُمنع من الدخول
        if ($user->role->value !== 'manager' && !$project->users->contains($user->id)) {
            return response()->json(['message' => 'غير مصرح لك بالوصول لهذا المشروع.'], 403);
        }

        return response()->json([
            'data' => $project->load(['departments', 'users'])
        ]);
    }

    // تحديث المشروع (الصلاحيات والـ Validation في UpdateProjectRequest)
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $validated = $request->validated();

        $project->update($validated);

        if (isset($validated['department_ids'])) {
            $project->departments()->sync($validated['department_ids']);
        }
        if (isset($validated['user_ids'])) {
            $project->users()->sync($validated['user_ids']);
        }

        return response()->json([
            'message' => 'تم تحديث المشروع بنجاح.',
            'data' => $project->load(['departments', 'users'])
        ]);
    }

    // حذف المشروع (للمدير فقط)
    public function destroy(Project $project)
    {
        if (auth()->user()->role->value !== 'manager') {
            return response()->json(['message' => 'غير مصرح لك.'], 403);
        }

        $project->delete();

        return response()->json(['message' => 'تم حذف المشروع بنجاح.']);
    }
}

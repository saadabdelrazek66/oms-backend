<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    // عرض الأقسام مع عدد الموظفين في كل قسم (للوحة المدير)
    public function index()
    {
        $departments = Department::withCount('users')->orderBy('id', 'desc')->paginate(15);
        return response()->json($departments);
    }

    // جلب كل الأقسام بدون تقسيم (للقوائم المنسدلة عند إضافة موظف)
    public function listAll()
    {
        return response()->json(Department::select('id', 'name')->get());
    }

    // إضافة قسم جديد
    public function store(DepartmentRequest $request)
    {
        $department = Department::create($request->validated());
        return response()->json([
            'message' => 'تم إنشاء القسم بنجاح',
            'data' => $department
        ], 201);
    }

    // تعديل قسم موجود
    public function update(DepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());
        return response()->json([
            'message' => 'تم تعديل القسم بنجاح',
            'data' => $department
        ]);
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(['message' => 'تم حذف القسم بنجاح']);
    }
}

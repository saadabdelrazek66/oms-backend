<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string'
        ]);

        $department = Department::create($validated);
        return response()->json(['message' => 'تم إنشاء القسم بنجاح', 'data' => $department], 201);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'description' => 'nullable|string'
        ]);

        $department->update($validated);
        return response()->json(['message' => 'تم تعديل القسم بنجاح', 'data' => $department]);
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(['message' => 'تم حذف القسم بنجاح']);
    }
}

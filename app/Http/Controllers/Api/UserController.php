<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Enums\Role;
use App\Enums\WorkType;
use Illuminate\Validation\Rules\Enum;
use App\Models\User;
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function store(Request $request)
    {
        // التحقق من صحة البيانات القادمة من الفرونت إند
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', new Enum(Role::class)],
            'phone' => 'required|string|max:20',
            'work_type' => ['required', new Enum(WorkType::class)],
        ]);

        $user = $this->userService->createUser($validated);

        return response()->json([
            'message' => 'تم إنشاء المستخدم بنجاح',
            'data' => $user
        ], 201);
    }

    public function index(Request $request)
    {
        // استخراج الفلاتر المطلوبة من الرابط
        $filters = $request->only(['search', 'role', 'work_type']);
        $perPage = $request->input('per_page', 10);

        $users = $this->userService->getAllUsers($filters, $perPage);

        // إرجاع استجابة لارافيل الافتراضية للـ Pagination
        return response()->json($users);
    }

    // تعديل مستخدم موجود
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => ['sometimes', 'required', new Enum(Role::class)],
            'phone' => 'sometimes|required|string|max:20',
            'work_type' => ['sometimes', 'required', new Enum(WorkType::class)],
        ]);

        $updatedUser = $this->userService->updateUser($user, $validated);

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'data' => $updatedUser
        ]);
    }

    // حذف مستخدم
    public function destroy(User $user)
    {
        $this->userService->deleteUser($user);

        return response()->json([
            'message' => 'تم الحذف بنجاح'
        ]);
    }
}

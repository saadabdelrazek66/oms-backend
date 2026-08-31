<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function createUser(array $data)
    {
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }

    // جلب كل المستخدمين
    public function getAllUsers(array $filters, int $perPage = 10)
    {
        $query = User::query();

        // فلتر البحث بالاسم أو الإيميل
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        // فلتر الدور
        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        // فلتر نوع العمل
        if (!empty($filters['work_type'])) {
            $query->where('work_type', $filters['work_type']);
        }

        // إرجاع البيانات بنظام الـ Pagination
        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    // تعديل بيانات مستخدم
    public function updateUser(User $user, array $data)
    {
        // إذا تم إرسال كلمة مرور جديدة، قم بتشفيرها
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // إذا لم يتم إرسالها، احذفها من المصفوفة حتى لا تتأثر القديمة
            unset($data['password']);
        }

        $user->update($data);
        return $user;
    }

    // حذف مستخدم
    public function deleteUser(User $user)
    {
        $user->delete();
    }
}

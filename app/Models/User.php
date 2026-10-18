<?php

namespace App\Models;

use App\Models\Permission;
use App\Services\PermissionPresetService;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\Role;
use App\Enums\WorkType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name','job_title', 'email', 'password', 'role',
        'has_custom_permissions', 'phone','work_type', 'work_start_time', 'work_end_time', 'daily_work_hours', 'work_start_day', 'work_end_day', 'working_days'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, LogsActivity, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $appends = ['permission_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'work_type' => WorkType::class,
            'daily_work_hours' => 'float',
            'working_days' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('User')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} بيانات الموظف");
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    /**
     * فحص ما إذا كان يوم محدد هو يوم عمل لهذا الموظف
     */
    public function isWorkingDay($date): bool
    {
        $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $dayName = strtolower($carbon->format('l'));

        $days = $this->working_days;
        if (empty($days)) {
            // استرجاع أيام العطلة المعتمدة من إعدادات المنظومة ديناميكياً
            try {
                $weekendDays = app(\App\Services\SettingService::class)->get('weekend_days', ['friday']);
                return !in_array($dayName, array_map('strtolower', (array) $weekendDays));
            } catch (\Throwable $e) {
                return $dayName !== 'friday';
            }
        }
        return in_array($dayName, array_map('strtolower', $days));
    }

    /**
     * الحصول على يوم العمل التالي للموظف مع تخطي أيام العطلة الأسبوعية
     */
    public function getNextWorkingDay($date): \Carbon\Carbon
    {
        $next = ($date instanceof \Carbon\Carbon ? $date->copy() : \Carbon\Carbon::parse($date))->addDay()->startOfDay();
        $safety = 0;
        while (!$this->isWorkingDay($next) && $safety < 14) {
            $next->addDay();
            $safety++;
        }
        return $next;
    }

    /**
     * الحصول على سعة ساعات العمل اليومية المعتمدة للموظف ديناميكياً من الإعدادات
     */
    public function getDailyCapacity(): float
    {
        try {
            $defaultDaily = (float) app(\App\Services\SettingService::class)->get('default_daily_hours', 8.0);
        } catch (\Throwable $e) {
            $defaultDaily = 8.0;
        }

        return (float) ($this->daily_work_hours && (float) $this->daily_work_hours > 0 ? $this->daily_work_hours : $defaultDaily);
    }

    // علاقة الموظف بالخطط المشترك بها
    public function contentPlans()
    {
        return $this->belongsToMany(ContentPlan::class, 'content_plan_user')
            ->withPivot('task_role')
            ->withTimestamps();
    }

    // جلب جميع الأقسام (الأساسية والفرعية) التي يتواجد بها الموظف
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    // جلب القسم الأساسي فقط للموظف
    public function primaryDepartment()
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->wherePivot('is_primary', true)
            ->withTimestamps();
    }

    // المشاريع المصرح للمستخدم الدخول إليها
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    // المهام التي قام المستخدم بإنشائها
    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    // المهام المسندة إلى المستخدم لتنفيذها
    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    // --- نظام الصلاحيات (RBAC Permissions) ---

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withTimestamps();
    }

    public function isManager(): bool
    {
        $roleValue = is_object($this->role) ? ($this->role->value ?? '') : (string)($this->role ?? '');
        return strtolower($roleValue) === 'manager';
    }

    public function getPermissionCodes(): array
    {
        if ($this->isManager()) {
            return Permission::pluck('code')->toArray();
        }

        if ($this->relationLoaded('permissions')) {
            $codes = $this->permissions->pluck('code')->toArray();
        } else {
            $codes = $this->permissions()->pluck('code')->toArray();
        }

        // إذا تم تخصيص صلاحيات للموظف (سواء فارغة أو محددة)، نعتمدها ولا نعود للـ preset
        if ($this->has_custom_permissions) {
            return $codes;
        }

        // إذا لم يكن للموظف أي صلاحية مخصصة، نمنحه الصلاحيات الافتراضية المناسبة لوظيفته
        if (empty($codes)) {
            return PermissionPresetService::getDefaultPermissionsForUser($this);
        }

        return $codes;
    }

    public function getPermissionCodesAttribute(): array
    {
        return $this->getPermissionCodes();
    }

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->isManager()) {
            return true;
        }

        return in_array($permissionCode, $this->getPermissionCodes());
    }

    public function hasAnyPermission(array $permissionCodes): bool
    {
        if ($this->isManager()) {
            return true;
        }

        $userCodes = $this->getPermissionCodes();
        return !empty(array_intersect($permissionCodes, $userCodes));
    }
}

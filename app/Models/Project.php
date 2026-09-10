<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
    ];

    // علاقة المشروع بالأقسام (Many-to-Many)
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_project');
    }

    // علاقة المشروع بأعضاء الفريق المصرح لهم بالدخول (Many-to-Many)
    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user');
    }

    // المهام التابعة لهذا المشروع
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}

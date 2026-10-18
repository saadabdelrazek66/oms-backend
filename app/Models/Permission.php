<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'module',
        'action',
        'name_ar',
        'description_ar',
        'sort_order',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')->withTimestamps();
    }
}

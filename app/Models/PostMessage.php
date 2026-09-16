<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_post_id',
        'user_id',
        'content',
        'type',
        'file_path',
    ];

    /**
     * علاقة الرسالة بالمنشور
     */
    public function post()
    {
        return $this->belongsTo(PlanPost::class, 'plan_post_id');
    }

    /**
     * علاقة الرسالة بالمستخدم (المرسل)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
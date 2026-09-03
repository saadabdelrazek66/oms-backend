<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientFollowUp extends Model
{
    protected $fillable = ['content_plan_id', 'user_id', 'content', 'image_path',];

    protected $appends = ['image_url'];

    // الخطة المرتبطة
    public function plan()
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    // الموظف الذي كتب التحديث
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

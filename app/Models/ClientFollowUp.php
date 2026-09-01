<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientFollowUp extends Model
{
    protected $fillable = ['content_plan_id', 'user_id', 'content'];

    // الخطة المرتبطة
    public function plan()
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    // الموظف الذي كتب التحديث
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

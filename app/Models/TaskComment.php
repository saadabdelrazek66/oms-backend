<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    protected $fillable = ['task_id', 'user_id', 'comment', 'is_deleted', 'is_edited'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

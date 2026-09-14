<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class ClientCredential extends Model
{
    use LogsActivity;
    protected $fillable = [
        'client_id', 'platform', 'login_url', 'username', 'password', 'two_factor_notes'
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('ClientCredential')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} بيانات دخول العميل");
    }
    // علاقة البيانات بالعميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

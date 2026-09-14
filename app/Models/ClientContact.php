<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class ClientContact extends Model
{
    use LogsActivity;
    protected $fillable = [
        'client_id',
        'contact_name',
        'contact_method',
        'contact_details',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()
            ->useLogName('ClientContact')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} جهة اتصال للعميل");
    }

    // علاقة جهة الاتصال بالعميل التابعة له
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

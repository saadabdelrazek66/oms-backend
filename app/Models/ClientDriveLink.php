<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientDriveLink extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'client_id',
        'title',
        'url',
    ];

    // إعدادات المراقبة (Audit Trail)
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ClientDriveLink')
            ->setDescriptionForEvent(fn(string $eventName) => "قام المستخدم بـ {$eventName} رابط درايف للعميل");
    }

    // العلاقة: الرابط ينتمي لعميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

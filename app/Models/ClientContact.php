<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    protected $fillable = [
        'client_id',
        'contact_name',
        'contact_method',
        'contact_details',
    ];

    // علاقة جهة الاتصال بالعميل التابعة له
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCredential extends Model
{
    protected $fillable = [
        'client_id', 'platform', 'login_url', 'username', 'password', 'two_factor_notes'
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    // علاقة البيانات بالعميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

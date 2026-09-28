<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'sms_log';

    public $timestamps = false;

    protected $fillable = ['to_name', 'phone', 'side', 'kind', 'source', 'text', 'state', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** فقط-درج (append-only) — بخش ۶ پلن. هرگز update/delete نشود. */
class CaseEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subject_type', 'subject_id', 'action_key', 'reason_id', 'reason_text',
        'description', 'admin_id', 'admin_role', 'ip', 'source', 'payload', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(Reason::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'support_id', 'request_id', 'from_donor_id', 'to_donor_id', 'mode', 'slot',
        'reason_id', 'reason_text', 'description', 'admin_id', 'created_at',
        'given_snapshot', 'months_snapshot',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function support(): BelongsTo
    {
        return $this->belongsTo(Support::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }

    public function fromDonor(): BelongsTo
    {
        return $this->belongsTo(Donor::class, 'from_donor_id');
    }

    public function toDonor(): BelongsTo
    {
        return $this->belongsTo(Donor::class, 'to_donor_id');
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

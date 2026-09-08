<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocRequestItem extends Model
{
    use HasFactory;

    protected $fillable = ['doc_request_id', 'label', 'type', 'required', 'order', 'path', 'filled_at'];

    protected $casts = [
        'required' => 'boolean',
        'filled_at' => 'datetime',
    ];

    public function docRequest(): BelongsTo
    {
        return $this->belongsTo(DocRequest::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundExpense extends Model
{
    protected $fillable = ['title', 'category', 'amount', 'spent_at', 'doc_path', 'by_id', 'description'];

    protected $casts = [
        'spent_at' => 'datetime',
    ];

    public function by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_id');
    }
}

<?php

namespace App\Models;

use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['assignee_id', 'subject_type', 'subject_id', 'title', 'priority', 'due_at', 'done_at'];

    protected $casts = [
        'due_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function priorityEnum(): TaskPriority
    {
        return TaskPriority::from($this->priority);
    }

    /** رنگ هشدار تأخیر از مقایسهٔ due_at با now — بخش ۳.۹ پلن، هرگز کلاس ثابت. */
    public function getIsOverdueAttribute(): bool
    {
        return ! $this->done_at && $this->due_at && $this->due_at->isPast();
    }
}

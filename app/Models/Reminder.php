<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    public const KIND_DEADLINE_AUTO = 'deadline_auto';

    public const KIND_MANUAL = 'manual';

    protected $fillable = [
        'user_id',
        'task_id',
        'kind',
        'remind_at',
        'message',
        'sent_at',
        'cancelled_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('sent_at')->whereNull('cancelled_at');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->pending()->where('remind_at', '<=', now());
    }

    public function isPending(): bool
    {
        return $this->sent_at === null && $this->cancelled_at === null;
    }
}

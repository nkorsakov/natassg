<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_id',
        'status_id',
        'priority_id',
        'type_id',
        'title',
        'note',
        'deadline',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TaskPriority::class, 'priority_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TaskType::class, 'type_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(CalendarEvent::class, 'task_event', 'task_id', 'event_id')
            ->withTimestamps();
    }

    public function advances(): BelongsToMany
    {
        return $this->belongsToMany(Advance::class, 'advance_task')
            ->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
